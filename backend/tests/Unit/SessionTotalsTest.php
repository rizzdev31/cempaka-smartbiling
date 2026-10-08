<?php

namespace Tests\Unit;

use App\Enums\SessionItemType;
use App\Support\Billing\SessionTotals;
use PHPUnit\Framework\TestCase;

/** Penjumlahan Open Tab — API.md §7 `totals`. */
class SessionTotalsTest extends TestCase
{
    private function item(SessionItemType $type, int $subtotal, bool $isPaid = false): object
    {
        return new class($type, $subtotal, $isPaid)
        {
            public function __construct(
                public SessionItemType $type,
                public int $subtotal,
                public bool $is_paid,
            ) {}
        };
    }

    public function test_open_tab_kosong(): void
    {
        $totals = SessionTotals::of([]);

        $this->assertSame(0, $totals->grandTotal());
        $this->assertSame(0, $totals->balanceDue());
    }

    public function test_contoh_dari_kontrak(): void
    {
        // Angka ini diambil persis dari contoh `totals` di API.md §7.
        $totals = SessionTotals::of([
            $this->item(SessionItemType::RENTAL, 20000, isPaid: true),
            $this->item(SessionItemType::FNB, 15000),
            $this->item(SessionItemType::EXTEND, 10000),
        ], paid: 20000);

        $this->assertSame([
            'rental' => 20000,
            'fnb' => 15000,
            'extend' => 10000,
            'discount' => 0,
            'adjustment' => 0,
            'grand_total' => 45000,
            'paid' => 20000,
            'balance_due' => 25000,
        ], $totals->toArray());
    }

    public function test_discount_mengurangi_karena_disimpan_negatif(): void
    {
        $totals = SessionTotals::of([
            $this->item(SessionItemType::RENTAL, 20000),
            $this->item(SessionItemType::DISCOUNT, -5000),
        ]);

        $this->assertSame(-5000, $totals->discount);
        $this->assertSame(15000, $totals->grandTotal());
    }

    public function test_overstay_masuk_sebagai_adjustment(): void
    {
        // DEC-023: kelebihan waktu ditagih lewat baris ADJUSTMENT, bukan
        // dengan menghitung ulang rental yang sudah dibayar.
        $totals = SessionTotals::of([
            $this->item(SessionItemType::RENTAL, 20000, isPaid: true),
            $this->item(SessionItemType::ADJUSTMENT, 10000),
        ], paid: 20000);

        $this->assertSame(20000, $totals->rental);
        $this->assertSame(10000, $totals->adjustment);
        $this->assertSame(10000, $totals->balanceDue());
    }

    public function test_item_belum_dibayar_dijumlahkan_terpisah(): void
    {
        $totals = SessionTotals::of([
            $this->item(SessionItemType::RENTAL, 20000, isPaid: true),
            $this->item(SessionItemType::FNB, 15000),
            $this->item(SessionItemType::EXTEND, 10000),
        ], paid: 20000);

        $this->assertSame(25000, $totals->unpaid);
    }

    public function test_kelebihan_bayar_tidak_jadi_tagihan_negatif(): void
    {
        // Kembalian tidak dicatat di V1 (API.md §7). Nilai negatif di sini
        // selalu berarti ada yang salah di tempat lain — jangan ditampilkan
        // ke operator sebagai tagihan minus.
        $totals = SessionTotals::of([
            $this->item(SessionItemType::RENTAL, 20000, isPaid: true),
        ], paid: 25000);

        $this->assertSame(0, $totals->balanceDue());
    }

    public function test_menerima_tipe_dalam_bentuk_string(): void
    {
        // Query mentah / data dari luar Eloquent tidak selalu sudah di-cast.
        $item = new class
        {
            public string $type = 'FNB';

            public int $subtotal = 7000;

            public bool $is_paid = false;
        };

        $this->assertSame(7000, SessionTotals::of([$item])->fnb);
    }
}
