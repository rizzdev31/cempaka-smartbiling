<?php

namespace Tests\Unit;

use App\Support\Billing\ExtendPolicy;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** Aturan extend — DEC-007. */
class ExtendPolicyTest extends TestCase
{
    private function endAt(): Carbon
    {
        return Carbon::parse('2026-10-08T08:00:00Z');
    }

    public function test_durasi_harus_kelipatan_tiga_puluh_menit(): void
    {
        $this->assertTrue(ExtendPolicy::isDurationValid(30));
        $this->assertTrue(ExtendPolicy::isDurationValid(60));
        $this->assertTrue(ExtendPolicy::isDurationValid(90));

        $this->assertFalse(ExtendPolicy::isDurationValid(45));
        $this->assertFalse(ExtendPolicy::isDurationValid(20));
        $this->assertFalse(ExtendPolicy::isDurationValid(0));
        $this->assertFalse(ExtendPolicy::isDurationValid(-30));
    }

    public function test_harga_extend_proporsional_dari_tarif_per_jam(): void
    {
        // harga = ceil(hourly_rate / 2 * (menit / 30))
        $this->assertSame(12500, ExtendPolicy::priceFor(25000, 30));
        $this->assertSame(25000, ExtendPolicy::priceFor(25000, 60));
        $this->assertSame(37500, ExtendPolicy::priceFor(25000, 90));
    }

    public function test_tarif_ganjil_dibulatkan_ke_atas(): void
    {
        // Tarif 21.667/jam datang dari paket 3 jam seharga 65.000.
        // Dibulatkan ke bawah, rental kehilangan rupiah di setiap extend.
        $this->assertSame(10834, ExtendPolicy::priceFor(21667, 30));
    }

    public function test_end_at_baru_dihitung_dari_end_at_lama_bukan_waktu_approve(): void
    {
        // Inti DEC-007. Operator approve telat 8 menit; customer tetap dapat
        // 30 menit dari end_at lama, bukan 38 menit.
        $baru = ExtendPolicy::newEndAt($this->endAt(), 30);

        $this->assertSame('2026-10-08T08:30:00Z', $baru->toIso8601ZuluString());
    }

    public function test_boleh_extend_selama_waktunya_belum_habis(): void
    {
        $endAt = $this->endAt();

        $this->assertTrue(ExtendPolicy::isWithinWindow($endAt, Carbon::parse('2026-10-08T07:00:00Z')));
        $this->assertTrue(ExtendPolicy::isWithinWindow($endAt, Carbon::parse('2026-10-08T07:59:59Z')));

        // Tepat di detik habis masih boleh — batasnya "<=", bukan "<".
        $this->assertTrue(ExtendPolicy::isWithinWindow($endAt, Carbon::parse('2026-10-08T08:00:00Z')));
    }

    public function test_ditolak_begitu_waktunya_lewat(): void
    {
        // DEC-033 mencabut grace 10 menit: "habis ya habis". Lewat satu detik
        // pun sudah tidak boleh — customer yang mau lanjut dibuatkan sesi baru.
        $endAt = $this->endAt();

        $this->assertFalse(ExtendPolicy::isWithinWindow($endAt, Carbon::parse('2026-10-08T08:00:01Z')));
        $this->assertFalse(ExtendPolicy::isWithinWindow($endAt, Carbon::parse('2026-10-08T08:05:00Z')));
        $this->assertFalse(ExtendPolicy::isWithinWindow($endAt, Carbon::parse('2026-10-08T08:10:00Z')));
    }

    public function test_deadline_sama_dengan_end_at(): void
    {
        // `extend_deadline_at` di API.md §7. Sejak DEC-033 nilainya sama persis
        // dengan `end_at`; field-nya sengaja tidak dihapus supaya client yang
        // sudah membacanya tidak patah.
        $this->assertSame(
            '2026-10-08T08:00:00Z',
            ExtendPolicy::deadlineFor($this->endAt())->toIso8601ZuluString(),
        );
    }

    public function test_menghitung_deadline_tidak_mengubah_end_at_aslinya(): void
    {
        // Carbon mutable: lupa copy() di sini membuat end_at session ikut
        // bergeser setiap kali response dibentuk.
        $endAt = $this->endAt();
        ExtendPolicy::deadlineFor($endAt);
        ExtendPolicy::newEndAt($endAt, 60);

        $this->assertSame('2026-10-08T08:00:00Z', $endAt->toIso8601ZuluString());
    }
}
