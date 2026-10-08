<?php

namespace App\Support\Billing;

use App\Enums\SessionItemType;

/**
 * Penjumlahan Open Tab — bentuk `totals` di API.md §7.
 *
 * Satu session punya satu Open Tab yang menampung rental, F&B, extend,
 * discount, dan adjustment (PRD §12). Kelas ini yang menjumlahkannya, supaya
 * `balance_due` tidak dihitung ulang dengan cara berbeda di tiap endpoint —
 * itu sumber klasik selisih kas.
 *
 * `DISCOUNT` disimpan sebagai angka negatif di `session_items.subtotal`
 * (kolomnya signed), jadi penjumlahannya tetap penambahan biasa.
 */
final class SessionTotals
{
    private function __construct(
        public readonly int $rental,
        public readonly int $fnb,
        public readonly int $extend,
        public readonly int $discount,
        public readonly int $adjustment,
        public readonly int $paid,
        public readonly int $unpaid,
    ) {}

    /**
     * @param  iterable<object{type: SessionItemType, subtotal: int, is_paid: bool}>  $items
     * @param  int  $paid  Jumlah payment berstatus CONFIRMED.
     */
    public static function of(iterable $items, int $paid = 0): self
    {
        $bucket = [
            SessionItemType::RENTAL->value => 0,
            SessionItemType::FNB->value => 0,
            SessionItemType::EXTEND->value => 0,
            SessionItemType::DISCOUNT->value => 0,
            SessionItemType::ADJUSTMENT->value => 0,
        ];

        $unpaid = 0;

        foreach ($items as $item) {
            $type = $item->type instanceof SessionItemType
                ? $item->type
                : SessionItemType::from((string) $item->type);

            $bucket[$type->value] += (int) $item->subtotal;

            if (! $item->is_paid) {
                $unpaid += (int) $item->subtotal;
            }
        }

        return new self(
            rental: $bucket[SessionItemType::RENTAL->value],
            fnb: $bucket[SessionItemType::FNB->value],
            extend: $bucket[SessionItemType::EXTEND->value],
            discount: $bucket[SessionItemType::DISCOUNT->value],
            adjustment: $bucket[SessionItemType::ADJUSTMENT->value],
            paid: $paid,
            unpaid: $unpaid,
        );
    }

    /**
     * Mengganti nilai rental — DEC-034.
     *
     * Dipakai Postpaid terbuka, yang rental-nya belum punya baris tersimpan
     * selama sesi berjalan. MENGGANTI, bukan menambah: setelah checkout baris
     * rental sudah ada dan nilainya sama, jadi menambah akan menagih dua kali.
     */
    public function withRental(int $rental): self
    {
        return new self(
            rental: $rental,
            fnb: $this->fnb,
            extend: $this->extend,
            discount: $this->discount,
            adjustment: $this->adjustment,
            paid: $this->paid,
            unpaid: $this->unpaid,
        );
    }

    public function grandTotal(): int
    {
        return $this->rental + $this->fnb + $this->extend + $this->discount + $this->adjustment;
    }

    /**
     * Yang ditagih saat checkout.
     *
     * Dijaga tidak negatif: kelebihan bayar tidak boleh tampil sebagai tagihan
     * minus di tablet. Kembalian tidak dicatat di V1 (API.md §7), jadi nilai
     * negatif di sini selalu berarti ada yang salah di tempat lain.
     */
    public function balanceDue(): int
    {
        return max(0, $this->grandTotal() - $this->paid);
    }

    /** Bentuk persis `totals` di API.md §7 — jangan diubah tanpa entry CHANGELOG. */
    public function toArray(): array
    {
        return [
            'rental' => $this->rental,
            'fnb' => $this->fnb,
            'extend' => $this->extend,
            'discount' => $this->discount,
            'adjustment' => $this->adjustment,
            'grand_total' => $this->grandTotal(),
            'paid' => $this->paid,
            'balance_due' => $this->balanceDue(),
        ];
    }
}
