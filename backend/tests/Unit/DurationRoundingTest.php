<?php

namespace Tests\Unit;

use App\Support\Billing\DurationRounding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pembulatan durasi — DEC-009.
 *
 * Empat angka di `test_contoh_wajib_dec_009` adalah contoh yang disebut
 * langsung di keputusan. Kalau salah satu berubah, yang berubah adalah
 * aturan bisnisnya, bukan kodenya — perlu entry DECISION-LOG baru.
 */
class DurationRoundingTest extends TestCase
{
    /** @return array<string, array{int, int}> */
    public static function contohDec009(): array
    {
        return [
            '35 menit -> sisa 5, toleransi, ke bawah' => [35, 30],
            '63 menit -> sisa 3, toleransi, ke bawah' => [63, 60],
            '70 menit -> sisa 10, lewat toleransi, ke atas' => [70, 90],
            '95 menit -> sisa 5, toleransi, ke bawah' => [95, 90],
        ];
    }

    #[DataProvider('contohDec009')]
    public function test_contoh_wajib_dec_009(int $menit, int $harapan): void
    {
        $this->assertSame($harapan, DurationRounding::toBillableMinutes($menit));
    }

    public function test_batas_toleransi_tepat_lima_menit_dibulatkan_ke_bawah(): void
    {
        // Sisa tepat 5 masih "<= 5" — ini batas yang paling mudah salah tulis
        // sebagai "< 5", dan selisihnya satu blok penuh bagi customer.
        $this->assertSame(60, DurationRounding::toBillableMinutes(65));
        $this->assertSame(90, DurationRounding::toBillableMinutes(66));
    }

    public function test_minimum_tiga_puluh_menit(): void
    {
        // Main 3 menit tetap ditagih setengah jam: station sudah dipakai dan
        // tidak bisa dijual ke orang lain selama itu.
        $this->assertSame(30, DurationRounding::toBillableMinutes(1));
        $this->assertSame(30, DurationRounding::toBillableMinutes(3));
        $this->assertSame(30, DurationRounding::toBillableMinutes(30));
    }

    public function test_tanpa_lantai_minimum_sisa_kecil_jadi_nol(): void
    {
        // Jalur overstay (DEC-023 / OD-021): lewat 4 menit tidak ditagih.
        $this->assertSame(0, DurationRounding::toBillableMinutes(4, applyMinimum: false));
        $this->assertSame(0, DurationRounding::toBillableMinutes(0, applyMinimum: false));
        $this->assertSame(30, DurationRounding::toBillableMinutes(20, applyMinimum: false));
    }

    public function test_jumlah_blok_mengikuti_menit_yang_ditagih(): void
    {
        $this->assertSame(1, DurationRounding::toBlocks(35));
        $this->assertSame(3, DurationRounding::toBlocks(70));
        $this->assertSame(0, DurationRounding::toBlocks(4, applyMinimum: false));
    }
}
