<?php

namespace Tests\Unit;

use App\Models\Package;
use PHPUnit\Framework\TestCase;

/**
 * `hourly_rate` = price / (duration_minutes / 60) — API.md §6.
 *
 * Nilai ini jadi dasar rumus harga extend DEC-007, jadi salah di sini
 * membuat semua harga extend salah.
 */
class PackagePricingTest extends TestCase
{
    private function rate(int $price, int $minutes): int
    {
        $package = new Package(['price' => $price, 'duration_minutes' => $minutes]);

        return $package->hourlyRate();
    }

    public function test_paket_satu_jam_tarifnya_sama_dengan_harganya(): void
    {
        $this->assertSame(25000, $this->rate(25000, 60));
    }

    public function test_paket_dua_jam_dibagi_dua(): void
    {
        $this->assertSame(22500, $this->rate(45000, 120));
    }

    public function test_pembagian_tidak_bulat_dibulatkan_ke_atas(): void
    {
        // 65000 / 3 = 21666,67. Dibulatkan ke bawah (21666) membuat tarif per
        // jam lebih murah dari paketnya, dan harga extend ikut bocor.
        $this->assertSame(21667, $this->rate(65000, 180));
    }

    public function test_paket_setengah_jam_tarifnya_dua_kali(): void
    {
        $this->assertSame(30000, $this->rate(15000, 30));
    }
}
