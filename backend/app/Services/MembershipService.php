<?php

namespace App\Services;

use App\Enums\SessionItemType;
use App\Enums\SessionStatus;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran member — DEC-027, DEC-029.
 *
 * Alur yang dilayani: customer Prepaid berhenti lebih awal, operator menawarkan
 * membership supaya sisa waktunya tidak hangus (DEC-024). Sebelum DEC-027 ini
 * jalan buntu — operator menawarkan tapi tidak punya tombolnya.
 *
 * Biaya pendaftaran masuk Open Tab sesi yang sedang berjalan, bukan ditagih
 * terpisah: customer sudah berdiri di meja kasir dengan satu tagihan di depan
 * mata, dan menambah transaksi kedua hanya memperumit tanpa menambah kejelasan.
 */
class MembershipService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(Customer $customer, ?string $sessionId, ?string $tier, User $actor): Membership
    {
        return DB::transaction(function () use ($customer, $sessionId, $tier, $actor) {
            if ($customer->membership !== null) {
                throw ApiException::conflict(
                    ErrorCode::CUSTOMER_ALREADY_MEMBER,
                    "{$customer->name} sudah terdaftar sebagai member.",
                );
            }

            $fee = (int) config('billing.membership_fee');

            $membership = Membership::query()->create([
                'customer_id' => $customer->id,
                'tier' => $tier ?: 'SILVER',
                'is_active' => true,
                'joined_at' => Carbon::now(),
            ]);

            if ($sessionId !== null) {
                $this->chargeFeeToSession($sessionId, $customer, $fee, $actor);
            }

            $this->audit->forUser($actor, AuditAction::MEMBERSHIP_CREATED, [
                'subject_type' => 'customer',
                'subject_id' => $customer->id,
                'after' => [
                    'tier' => $membership->tier,
                    'fee' => $fee,
                    'session_id' => $sessionId,
                ],
            ]);

            return $membership->fresh('customer');
        });
    }

    /**
     * Biaya pendaftaran jadi item `ADJUSTMENT` pada sesi berjalan.
     *
     * Bukan `DISCOUNT` (itu negatif) dan bukan `FNB`. `ADJUSTMENT` adalah
     * tempat untuk apa pun yang bukan waktu bermain maupun makanan — sama
     * seperti kelebihan waktu di DEC-023.
     */
    private function chargeFeeToSession(string $sessionId, Customer $customer, int $fee, User $actor): void
    {
        $session = BillingSession::query()->lockForUpdate()->find($sessionId);

        if ($session === null) {
            throw ApiException::notFound('Sesi tidak ditemukan.');
        }

        if ($session->status->isFinal()) {
            throw ApiException::conflict(
                ErrorCode::SESSION_STATUS_INVALID,
                'Sesi sudah selesai, biaya pendaftaran tidak bisa ditambahkan ke sana.',
            );
        }

        /*
         * Sekalian menautkan sesi ke customer yang baru jadi member. Tanpa ini,
         * sesi yang dimulai sebagai Walk-in tetap tidak punya `customer_id`,
         * dan saldo sisa waktunya tidak akan pernah masuk ke akun siapa pun
         * saat checkout (DEC-024).
         */
        if ($session->customer_id === null && $session->status !== SessionStatus::COMPLETED) {
            $session->customer_id = $customer->id;
            $session->customer_name = null;
            $session->save();
        }

        $session->items()->create([
            'type' => SessionItemType::ADJUSTMENT,
            'name' => 'Biaya daftar member',
            'qty' => 1,
            'unit_price' => $fee,
            'subtotal' => $fee,
            'is_paid' => false,
            'meta' => ['membership_fee' => true],
            'created_by' => $actor->id,
        ]);
    }
}
