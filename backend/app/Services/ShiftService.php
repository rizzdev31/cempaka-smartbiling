<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Exceptions\ApiException;
use App\Models\BillingSession;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shift kasir — API.md §10, PRD §20.
 *
 * Tanpa shift, `shift_id` pada setiap payment dan session tetap NULL dan uang
 * yang masuk tidak bisa dihubungkan ke siapa yang jaga. Itu bukan masalah saat
 * uji coba, tapi tidak boleh dibawa ke uang nyata.
 *
 * DEC-013 (dasar laporan: saat item dibuat atau saat dibayar) belum diputuskan.
 * Lihat `summary()` untuk pilihan sementara yang diambil dan alasannya.
 */
class ShiftService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function open(User $actor, int $openingCash, ?string $note): Shift
    {
        return DB::transaction(function () use ($actor, $openingCash, $note) {
            /*
             * DEC-013 menetapkan satu tablet kasir per lokasi, tapi yang
             * dijaga di sini lebih sempit: satu operator tidak boleh punya dua
             * shift terbuka. Kalau boleh, payment-nya akan masuk ke shift yang
             * mana pun yang kebetulan ditemukan lebih dulu.
             */
            if ($actor->activeShift() !== null) {
                throw ApiException::conflict(
                    ErrorCode::SHIFT_ALREADY_OPEN,
                    'Masih ada shift yang terbuka. Tutup dulu sebelum membuka yang baru.',
                );
            }

            $shift = Shift::query()->create([
                'operator_id' => $actor->id,
                'opened_at' => Carbon::now(),
                'opening_cash' => $openingCash,
                'note' => $note,
            ]);

            $this->audit->forUser($actor, AuditAction::SHIFT_OPENED, [
                'subject_type' => 'shift',
                'subject_id' => $shift->id,
                'after' => ['opening_cash' => $openingCash],
            ]);

            return $shift->fresh('operator');
        });
    }

    public function close(Shift $shift, int $closingCash, ?string $note, User $actor): Shift
    {
        return DB::transaction(function () use ($shift, $closingCash, $note, $actor) {
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $shift->isOpen()) {
                throw ApiException::conflict(
                    ErrorCode::SHIFT_NOT_OPEN,
                    'Shift ini sudah ditutup.',
                );
            }

            /*
             * Shift orang lain hanya boleh ditutup admin ke atas. Operator yang
             * lupa menutup shiftnya harus bisa dibereskan tanpa menunggu dia
             * kembali, tapi bukan oleh sesama operator.
             */
            if ($shift->operator_id !== $actor->id && ! $actor->role->isAdminLevel()) {
                throw ApiException::forbidden('Hanya pemilik shift atau admin yang boleh menutup shift ini.');
            }

            $summary = $this->summary($shift);

            $shift->closed_at = Carbon::now();
            $shift->closing_cash = $closingCash;
            $shift->note = $note ?? $shift->note;
            $shift->save();

            /*
             * Selisih kas dicatat di audit, bukan di kolom tersendiri: nilainya
             * turunan (closing - opening - cash masuk) dan menyimpannya dua kali
             * berarti ada dua angka yang bisa berbeda. Yang perlu bertahan
             * adalah jejaknya.
             */
            $expected = (int) $shift->opening_cash + $summary['cash'];

            $this->audit->forUser($actor, AuditAction::SHIFT_CLOSED, [
                'subject_type' => 'shift',
                'subject_id' => $shift->id,
                'after' => [
                    'closing_cash' => $closingCash,
                    'expected_cash' => $expected,
                    'difference' => $closingCash - $expected,
                    'summary' => $summary,
                ],
            ]);

            return $shift->fresh('operator');
        });
    }

    /**
     * Ringkasan shift — API.md §10.
     *
     * `cash`, `qris`, dan `total` selalu **uang yang benar-benar masuk** selama
     * shift ini: dijumlahkan dari payment yang ber-`shift_id` sama. Tidak ada
     * ambiguitas di sana.
     *
     * `rental` dan `fnb` memakai **nilai transaksi** — item yang dibuat pada
     * sesi milik shift ini, dibayar maupun belum. Dasar yang lain (hanya yang
     * sudah dibayar) masih jadi **OD-013** dan belum diputuskan. Pilihan ini
     * diambil karena lebih menjawab pertanyaan "berapa yang terjual saat saya
     * jaga", dan karena uang masuknya sudah terwakili `cash`/`qris`.
     *
     * @return array{rental: int, fnb: int, cash: int, qris: int, total: int}
     */
    public function summary(Shift $shift): array
    {
        $payments = Payment::query()
            ->where('shift_id', $shift->id)
            ->where('status', 'CONFIRMED')
            ->get();

        $sessionIds = BillingSession::query()
            ->where('shift_id', $shift->id)
            ->pluck('id');

        $items = $sessionIds->isEmpty()
            ? collect()
            : DB::table('session_items')
                ->whereIn('session_id', $sessionIds)
                ->get();

        $byType = fn (SessionItemType $type) => (int) $items
            ->where('type', $type->value)
            ->sum('subtotal');

        $byMethod = fn (PaymentMethod $method) => (int) $payments
            ->where('method', $method)
            ->sum('amount');

        return [
            // Extend ikut rental: keduanya adalah penjualan waktu bermain.
            'rental' => $byType(SessionItemType::RENTAL) + $byType(SessionItemType::EXTEND),
            'fnb' => $byType(SessionItemType::FNB),
            'cash' => $byMethod(PaymentMethod::CASH),
            'qris' => $byMethod(PaymentMethod::QRIS_STATIC),
            'total' => (int) $payments->sum('amount'),
        ];
    }
}
