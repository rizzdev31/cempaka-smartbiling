<?php

namespace Tests\Unit;

use App\Support\Billing\OverstayPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Penagihan overstay — DEC-023.
 *
 * Hak waktu = durasi paket + total menit extend. Yang ditagih hanya menit
 * di luar itu; rental Prepaid yang sudah dibayar tidak dihitung ulang.
 */
class OverstayPolicyTest extends TestCase
{
    public function test_belum_lewat_tidak_ada_overstay(): void
    {
        $this->assertSame(0, OverstayPolicy::minutes(45, 60));
        $this->assertSame(0, OverstayPolicy::minutes(60, 60));
        $this->assertSame(0, OverstayPolicy::priceFor(20000, 45, 60));
    }

    public function test_hak_waktu_termasuk_extend(): void
    {
        // Paket 60 + extend 30 = 90. Main 95 menit -> overstay 5 menit saja.
        $this->assertSame(5, OverstayPolicy::minutes(95, 90));
    }

    public function test_lewat_dalam_toleransi_lima_menit_tidak_ditagih(): void
    {
        // DEC-009 dipakai ulang di sini (OD-021). Lewat 4 menit = 0 rupiah.
        $this->assertSame(0, OverstayPolicy::billableMinutes(64, 60));
        $this->assertSame(0, OverstayPolicy::priceFor(20000, 64, 60));

        // Tepat 5 menit masih dalam toleransi.
        $this->assertSame(0, OverstayPolicy::billableMinutes(65, 60));
    }

    public function test_lewat_toleransi_dibulatkan_ke_satu_blok(): void
    {
        // Lewat 6 menit -> 30 menit ditagih.
        $this->assertSame(30, OverstayPolicy::billableMinutes(66, 60));
        $this->assertSame(10000, OverstayPolicy::priceFor(20000, 66, 60));

        // Lewat 20 menit -> tetap 30 menit.
        $this->assertSame(30, OverstayPolicy::billableMinutes(80, 60));
    }

    public function test_tidak_ada_lantai_tiga_puluh_menit(): void
    {
        // Beda dengan rounding Postpaid: di sana minimum 30 menit, di sini
        // tidak. Kelebihan 2 menit tidak boleh ditagih setengah jam.
        $this->assertSame(0, OverstayPolicy::billableMinutes(62, 60));
    }

    public function test_overstay_panjang_ditagih_per_blok(): void
    {
        // Paket 60, main 130 menit -> lewat 70 -> sisa 10 > 5 -> 90 menit.
        $this->assertSame(90, OverstayPolicy::billableMinutes(130, 60));

        // 3 blok x (20000 / 2) = 30000.
        $this->assertSame(30000, OverstayPolicy::priceFor(20000, 130, 60));
    }

    public function test_harga_overstay_sama_dengan_harga_extend_untuk_durasi_sama(): void
    {
        // Kalau dua jalur ini berbeda harga, operator tidak akan bisa
        // menjelaskan ke customer kenapa 30 menit yang sama beda tagihan.
        $extend = \App\Support\Billing\ExtendPolicy::priceFor(21667, 30);
        $overstay = OverstayPolicy::priceFor(21667, 80, 60);

        $this->assertSame($extend, $overstay);
    }
}
