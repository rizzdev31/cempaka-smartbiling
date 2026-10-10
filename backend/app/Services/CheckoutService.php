<?php

namespace App\Services;

use App\Enums\CustomerCreditType;
use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Support\Realtime\SessionUpdateBroadcaster;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\CustomerCredit;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Billing\CheckoutBilling;
use App\Support\Billing\DocumentNumber;
use App\Support\Presenters\SessionPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Checkout — API.md §7, PRD §12, T13.
 *
 * Menghasilkan SATU final transaction: semua item unpaid ditagih sekaligus,
 * bukan satu pembayaran per item. Itu syarat yang membuat struk bisa
 * dijelaskan ke customer dalam satu lembar.
 *
 * Urutannya penting dan tidak boleh ditukar:
 *   1. kunci sesi, tutup waktunya (`ended_at`)
 *   2. hitung ulang rental
 *   3. pakai saldo member (mengurangi tagihan)
 *   4. baru cocokkan pembayaran dengan `balance_due`
 * Kalau pembayaran dicocokkan sebelum langkah 2 dan 3, angkanya adalah tagihan
 * yang sudah basi.
 */
class CheckoutService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array{method: string, amount: int, reference?: ?string}>  $payments
     * @return array{session: BillingSession, receipt: array}
     */
    public function checkout(BillingSession $session, array $payments, bool $useCredit, User $actor): array
    {
        $result = DB::transaction(function () use ($session, $payments, $useCredit, $actor) {
            $now = Carbon::now();

            $session = BillingSession::query()
                ->lockForUpdate()
                ->findOrFail($session->id)
                ->load(['items', 'payments', 'customer.membership', 'station']);

            $session->transitionTo(SessionStatus::CHECKOUT);

            $actualMinutes = $session->actualMinutes($now);

            $billing = CheckoutBilling::compute(
                $session->mode,
                (int) $session->package_duration_minutes,
                (int) $session->package_price,
                (int) $session->hourly_rate,
                $session->entitledMinutes() - (int) $session->package_duration_minutes,
                $actualMinutes,
            );

            $this->applyRental($session, $billing);

            $session->load('items');

            $creditUsed = $useCredit ? $this->applyCredit($session, $actor) : 0;

            $session->load('items');
            $balanceDue = $session->totals()->balanceDue();

            $this->settlePayments($session, $payments, $balanceDue, $now, $actor);

            // Semua tagihan tertutup — dari sini tidak ada item yang boleh
            // tertinggal sebagai unpaid, kalau tidak laporan shift akan
            // menampilkan piutang yang sebenarnya sudah dibayar.
            $session->items()->where('is_paid', false)->update(['is_paid' => true]);

            $creditEarned = $this->awardLeftoverCredit($session, $billing, $actor);

            // Lewat penjagaan PRD §11 juga di langkah terakhir — CHECKOUT
            // adalah satu-satunya status yang boleh menjadi COMPLETED.
            $session->transitionTo(SessionStatus::COMPLETED);
            $session->ended_at = $now;
            $session->actual_duration_minutes = $actualMinutes;
            $session->billable_duration_minutes = $billing['billable_minutes'];
            $session->receipt_number = DocumentNumber::receiptNumber($now);
            $session->receipt_issued_at = $now;
            $session->closed_by = $actor->id;
            $session->save();

            $session->load(['items', 'payments', 'station', 'customer']);

            $this->audit->forUser($actor, AuditAction::SESSION_CHECKOUT, [
                'subject_type' => 'session',
                'subject_id' => $session->id,
                'after' => [
                    'receipt_number' => $session->receipt_number,
                    'actual_duration_minutes' => $actualMinutes,
                    'billable_duration_minutes' => $billing['billable_minutes'],
                    'grand_total' => $session->totals()->grandTotal(),
                    'credit_used' => $creditUsed,
                    'credit_earned' => $creditEarned,
                ],
            ]);

            return [
                'session' => $session,
                'receipt' => $this->receipt($session, $actualMinutes, $billing, $actor),
            ];
        });

        SessionUpdateBroadcaster::schedule($result['session'], ['status', 'totals']);

        return $result;
    }

    /**
     * Postpaid: rental dihitung ulang dari durasi aktual (DEC-009).
     * Prepaid: harga paket tidak disentuh (DEC-024).
     */
    private function applyRental(BillingSession $session, array $billing): void
    {
        if ($session->mode !== SessionMode::POSTPAID) {
            return;
        }

        $attributes = [
            'name' => "Paket {$session->package_name} ({$billing['rental_minutes']} menit)",
            'unit_price' => $billing['rental_price'],
            'subtotal' => $billing['rental_price'],
        ];

        $rental = $session->items()->where('type', SessionItemType::RENTAL->value)->first();

        /*
         * Postpaid terbuka tidak punya baris rental selama sesi berjalan
         * (DEC-034) — barisnya lahir di sini, saat angkanya sudah pasti.
         */
        if ($rental === null) {
            $session->items()->create($attributes + [
                'type' => SessionItemType::RENTAL,
                'qty' => 1,
                'is_paid' => false,
                'meta' => ['duration_minutes' => $billing['rental_minutes']],
            ]);

            return;
        }

        $rental->update($attributes);
    }

    /**
     * Memakai saldo member — DEC-026.
     *
     * Saldo mengurangi tagihan apa pun, bukan hanya rental ("digabungkan dengan
     * tambahan biling lainnya"). Dicatat sebagai item `DISCOUNT` supaya muncul
     * di struk, dan sebagai baris ledger `USED` supaya saldonya punya jejak.
     */
    private function applyCredit(BillingSession $session, User $actor): int
    {
        $customer = $session->customer;

        if ($customer === null || ! $customer->isActiveMember()) {
            return 0;
        }

        $balance = $customer->creditBalance();
        $due = $session->totals()->balanceDue();
        $use = min($balance, $due);

        if ($use <= 0) {
            return 0;
        }

        $session->items()->create([
            'type' => SessionItemType::DISCOUNT,
            'name' => 'Saldo member',
            'qty' => 1,
            // DISCOUNT disimpan negatif — kolom uang item memang signed.
            'unit_price' => -$use,
            'subtotal' => -$use,
            'is_paid' => true,
            'meta' => ['credit' => true],
            'created_by' => $actor->id,
        ]);

        CustomerCredit::query()->create([
            'customer_id' => $customer->id,
            'type' => CustomerCreditType::USED,
            'amount' => -$use,
            'session_id' => $session->id,
            'note' => "Dipakai di sesi {$session->code}",
            'created_by' => $actor->id,
        ]);

        $this->audit->forUser($actor, AuditAction::CREDIT_USED, [
            'subject_type' => 'customer',
            'subject_id' => $customer->id,
            'after' => ['amount' => $use, 'session_id' => $session->id],
        ]);

        return $use;
    }

    /**
     * Menyimpan sisa waktu member — DEC-024, DEC-026.
     *
     * Non-member: sisa hangus, tidak ada baris apa pun. Itu bukan kelalaian —
     * itu keputusannya.
     */
    private function awardLeftoverCredit(BillingSession $session, array $billing, User $actor): int
    {
        $value = $billing['leftover_value'];
        $customer = $session->customer;

        if ($value <= 0 || $customer === null || ! $customer->isActiveMember()) {
            return 0;
        }

        CustomerCredit::query()->create([
            'customer_id' => $customer->id,
            'type' => CustomerCreditType::EARNED,
            'amount' => $value,
            'session_id' => $session->id,
            'note' => "Sisa {$billing['leftover_minutes']} menit dari sesi {$session->code}",
            'created_by' => $actor->id,
        ]);

        $this->audit->forUser($actor, AuditAction::CREDIT_EARNED, [
            'subject_type' => 'customer',
            'subject_id' => $customer->id,
            'after' => [
                'amount' => $value,
                'leftover_minutes' => $billing['leftover_minutes'],
                'session_id' => $session->id,
            ],
        ]);

        return $value;
    }

    /**
     * @param  array<int, array{method: string, amount: int, reference?: ?string}>  $payments
     */
    private function settlePayments(
        BillingSession $session,
        array $payments,
        int $balanceDue,
        Carbon $now,
        User $actor,
    ): void {
        $total = array_sum(array_map(fn (array $p) => (int) $p['amount'], $payments));

        if ($total < $balanceDue) {
            throw ApiException::unprocessable(
                ErrorCode::CHECKOUT_INSUFFICIENT_PAYMENT,
                'Pembayaran belum menutupi tagihan.',
                ['balance_due' => $balanceDue, 'paid' => $total],
            );
        }

        // Kembalian tidak dicatat di V1 (API.md §7), jadi kelebihan ditolak
        // dan bukan diam-diam dicatat sebagai pendapatan.
        if ($total > $balanceDue) {
            throw ApiException::unprocessable(
                ErrorCode::PAYMENT_AMOUNT_EXCEEDS_BALANCE,
                'Jumlah pembayaran melebihi tagihan.',
                ['balance_due' => $balanceDue, 'paid' => $total],
            );
        }

        foreach ($payments as $payment) {
            $method = PaymentMethod::from($payment['method']);
            $reference = trim((string) ($payment['reference'] ?? '')) ?: null;

            if ($method->requiresReference() && $reference === null) {
                throw ApiException::unprocessable(
                    ErrorCode::PAYMENT_REFERENCE_REQUIRED,
                    'Pembayaran QRIS wajib mencantumkan nomor referensi.',
                    ['reference' => 'Wajib diisi untuk QRIS.'],
                );
            }

            $session->payments()->create([
                'method' => $method,
                'amount' => (int) $payment['amount'],
                'status' => 'CONFIRMED',
                'reference' => $reference,
                'actor_id' => $actor->id,
                'shift_id' => $actor->activeShift()?->id,
                'confirmed_at' => $now,
            ]);
        }

        $session->load('payments');
    }

    /**
     * `actual_duration_minutes` DAN `billable_duration_minutes` wajib keduanya
     * (API.md §7) — supaya operator bisa menjelaskan kenapa 63 menit ditagih 60.
     */
    private function receipt(BillingSession $session, int $actualMinutes, array $billing, User $actor): array
    {
        $totals = $session->totals();

        return [
            'number' => $session->receipt_number,
            'issued_at' => $session->receipt_issued_at?->toIso8601ZuluString(),
            'actual_duration_minutes' => $actualMinutes,
            'billable_duration_minutes' => $billing['billable_minutes'],
            'lines' => $session->items->map(fn ($item) => [
                'name' => $item->name,
                'qty' => (int) $item->qty,
                'subtotal' => (int) $item->subtotal,
            ])->all(),
            'totals' => [
                'grand_total' => $totals->grandTotal(),
                'paid' => $totals->paid,
                'balance_due' => $totals->balanceDue(),
            ],
            /*
             * Objek payment UTUH, bukan hanya method + amount.
             *
             * Struk adalah catatan permanen: "pembayaran mana yang melunasi
             * tagihan ini" harus bisa dijawab berbulan-bulan kemudian, dan
             * untuk itu butuh id, waktu konfirmasi, dan siapa yang menerima.
             * Bentuknya dibuat sama persis dengan payment di tempat lain
             * supaya client tidak perlu dua cara membacanya.
             */
            'payments' => $session->payments
                ->map(fn ($p) => SessionPresenter::payment($p))
                ->all(),
            'operator' => ['id' => $actor->id, 'name' => $actor->name],
        ];
    }
}
