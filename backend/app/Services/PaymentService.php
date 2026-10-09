<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Enums\SessionStatus;
use App\Events\PaymentConfirmed;
use App\Events\SessionStarted;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Payment;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Realtime\SessionUpdateBroadcaster;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran manual — API.md §7 `POST /sessions/{id}/payments`, PRD §21.
 *
 * V1 hanya Cash dan QRIS statis, keduanya dikonfirmasi operator saat dibuat.
 * Gateway (Tahap 3D) nanti memakai status PENDING -> CONFIRMED yang sudah
 * disiapkan kolomnya.
 *
 * Perlindungan duplicate ada di middleware `Idempotency-Key`, bukan di sini —
 * R06. Service ini hanya memastikan jumlahnya tidak melebihi tagihan.
 */
class PaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function record(BillingSession $session, array $input, User $actor): Payment
    {
        return DB::transaction(function () use ($session, $input, $actor) {
            $now = Carbon::now();

            /*
             * Dikunci supaya dua pembayaran bersamaan tidak sama-sama melihat
             * balance lama dan keduanya lolos pemeriksaan jumlah.
             */
            $session = BillingSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id)
                ->load(['items', 'payments']);

            if ($session->status->isFinal()) {
                throw ApiException::conflict(
                    ErrorCode::SESSION_STATUS_INVALID,
                    "Sesi berstatus {$session->status->value} tidak bisa menerima pembayaran.",
                );
            }

            $method = PaymentMethod::from($input['method']);
            $reference = trim((string) ($input['reference'] ?? '')) ?: null;

            // QRIS statis tidak punya callback gateway — nomor referensi adalah
            // satu-satunya bukti yang bisa dicocokkan saat rekonsiliasi kas.
            if ($method->requiresReference() && $reference === null) {
                throw ApiException::unprocessable(
                    ErrorCode::PAYMENT_REFERENCE_REQUIRED,
                    'Pembayaran QRIS wajib mencantumkan nomor referensi.',
                    ['reference' => 'Wajib diisi untuk QRIS.'],
                );
            }

            $amount = (int) $input['amount'];
            $balanceDue = $session->totals()->balanceDue();

            // Kembalian tidak dicatat di V1 (API.md §7). Membiarkan kelebihan
            // masuk membuat laporan kas tidak bisa dicocokkan.
            if ($amount > $balanceDue) {
                throw ApiException::unprocessable(
                    ErrorCode::PAYMENT_AMOUNT_EXCEEDS_BALANCE,
                    'Jumlah pembayaran melebihi tagihan.',
                    ['amount' => $amount, 'balance_due' => $balanceDue],
                );
            }

            $payment = $session->payments()->create([
                'method' => $method,
                'amount' => $amount,
                'status' => 'CONFIRMED',
                'reference' => $reference,
                'note' => $input['note'] ?? null,
                'actor_id' => $actor->id,
                'shift_id' => $actor->activeShift()?->id,
                'confirmed_at' => $now,
            ]);

            $session->load(['items', 'payments']);

            $this->audit->forUser($actor, AuditAction::PAYMENT_CONFIRMED, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'after' => [
                    'payment_id' => $payment->id,
                    'method' => $method->value,
                    'amount' => $amount,
                    'reference' => $reference,
                ],
            ]);

            $statusBefore = $session->status;

            $this->settle($session, $actor, $now);

            $session->refresh()->load(['station', 'customer', 'items', 'payments']);

            /*
             * Prepaid baru "mulai" di sini: sesi dibuat lebih dulu dan timernya
             * menunggu uang masuk. Karena itu session.started dipicu dari
             * pembayaran, bukan dari pembuatan sesi.
             */
            if ($statusBefore === SessionStatus::PENDING_PAYMENT && $session->status === SessionStatus::ACTIVE) {
                SessionStarted::dispatch($session);
            }

            PaymentConfirmed::dispatch($session, $payment, $actor);
            SessionUpdateBroadcaster::schedule($session, ['totals', 'status']);

            return $payment;
        });
    }

    /**
     * Menyalakan timer dan menandai item lunas setelah uang masuk.
     *
     * Prepaid baru mulai berjalan di sini, bukan saat session dibuat: PRD §11
     * menaruh `PENDING_PAYMENT` justru supaya station tidak terpakai oleh sesi
     * yang belum dibayar.
     */
    private function settle(BillingSession $session, User $actor, Carbon $now): void
    {
        $totals = $session->totals();

        if ($session->status === SessionStatus::PENDING_PAYMENT && $totals->paid >= $totals->rental) {
            $session->transitionTo(SessionStatus::ACTIVE);
            $session->started_at = $now;
            $session->end_at = $now->copy()->addMinutes((int) $session->package_duration_minutes);
            $session->save();

            $this->audit->forUser($actor, AuditAction::SESSION_ACTIVATED, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'after' => [
                    'started_at' => $session->started_at->toIso8601ZuluString(),
                    'end_at' => $session->end_at->toIso8601ZuluString(),
                ],
            ]);

            $session->items()
                ->where('type', SessionItemType::RENTAL->value)
                ->update(['is_paid' => true]);
        }

        // Semua tagihan tertutup — tandai sisa item lunas supaya `unpaid`
        // tidak terus menghitung barang yang sudah dibayar.
        if ($session->totals()->balanceDue() === 0) {
            $session->items()->where('is_paid', false)->update(['is_paid' => true]);
        }

        $session->load(['items', 'payments']);
    }
}
