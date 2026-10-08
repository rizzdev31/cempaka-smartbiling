<?php

namespace Tests\Unit;

use App\Enums\SessionMode;
use App\Support\Billing\CheckoutBilling;
use PHPUnit\Framework\TestCase;

/**
 * Perhitungan checkout — API.md §7, DEC-009, DEC-023, DEC-024, DEC-026.
 *
 * Paket acuan di seluruh berkas ini: 1 jam, 20.000, jadi `hourly_rate` 20.000
 * dan satu blok 30 menit = 10.000.
 */
class CheckoutBillingTest extends TestCase
{
    private function postpaid(int $extendMinutes, int $actualMinutes): array
    {
        return CheckoutBilling::compute(
            SessionMode::POSTPAID, 60, 20000, 20000, $extendMinutes, $actualMinutes,
        );
    }

    private function prepaid(int $extendMinutes, int $actualMinutes): array
    {
        return CheckoutBilling::compute(
            SessionMode::PREPAID, 60, 20000, 20000, $extendMinutes, $actualMinutes,
        );
    }

    public function test_postpaid_ditagih_dari_durasi_aktual(): void
    {
        // 63 menit -> sisa 3, toleransi DEC-009 -> 60 menit -> 20.000.
        $billing = $this->postpaid(0, 63);

        $this->assertSame(60, $billing['billable_minutes']);
        $this->assertSame(20000, $billing['rental_price']);
    }

    public function test_postpaid_dengan_extend_tidak_menagih_menit_yang_sama_dua_kali(): void
    {
        // Paket 60 + extend 30, main 90 menit.
        //
        // Tanpa pengurangan menit extend, rental akan dihitung 90 menit
        // (30.000) DITAMBAH item extend 10.000 — 30 menit dibayar dua kali.
        $billing = $this->postpaid(30, 90);

        $this->assertSame(90, $billing['billable_minutes']);
        $this->assertSame(60, $billing['rental_minutes']);
        $this->assertSame(20000, $billing['rental_price']);

        // rental 20.000 + extend 10.000 = 30.000 = 90 menit x 20.000/jam.
        $this->assertSame(30000, $billing['rental_price'] + 10000);
    }

    public function test_postpaid_tidak_punya_overstay(): void
    {
        // Rental Postpaid sudah dihitung dari durasi aktual, jadi tidak ada
        // kelebihan yang belum tertagih.
        $billing = $this->postpaid(0, 200);

        $this->assertSame(0, $billing['overstay_minutes']);
        $this->assertSame(0, $billing['overstay_price']);
    }

    public function test_postpaid_minimum_tiga_puluh_menit(): void
    {
        $billing = $this->postpaid(0, 4);

        $this->assertSame(30, $billing['billable_minutes']);
        $this->assertSame(10000, $billing['rental_price']);
    }

    public function test_prepaid_rental_tetap_harga_paket(): void
    {
        // DEC-024: berhenti di menit ke-40 tetap bayar satu jam penuh.
        $billing = $this->prepaid(0, 40);

        $this->assertSame(20000, $billing['rental_price']);
        $this->assertSame(60, $billing['rental_minutes']);
    }

    public function test_prepaid_lewat_dalam_toleransi_tidak_ditagih(): void
    {
        // Lewat 4 menit — DEC-023 memakai toleransi 5 menit DEC-009.
        $billing = $this->prepaid(0, 64);

        $this->assertSame(0, $billing['overstay_price']);
        $this->assertSame(60, $billing['billable_minutes']);
    }

    public function test_prepaid_overstay_ditagih_per_blok(): void
    {
        // Main 90 menit dengan paket 60 tanpa extend -> lewat 30 -> 10.000.
        $billing = $this->prepaid(0, 90);

        $this->assertSame(30, $billing['overstay_minutes']);
        $this->assertSame(10000, $billing['overstay_price']);
        $this->assertSame(90, $billing['billable_minutes']);

        // Rental tidak ikut berubah — PRD §12 "tidak ada pembayaran rental kedua".
        $this->assertSame(20000, $billing['rental_price']);
    }

    public function test_extend_menambah_hak_waktu_sehingga_overstay_berkurang(): void
    {
        // Paket 60 + extend 30 = hak 90 menit. Main 90 -> tidak ada overstay.
        $billing = $this->prepaid(30, 90);

        $this->assertSame(0, $billing['overstay_minutes']);
        $this->assertSame(90, $billing['billable_minutes']);
    }

    public function test_sisa_waktu_prepaid_dihitung_proporsional_per_menit(): void
    {
        // DEC-026: "sisa waktu berapapun" — tidak dibulatkan ke blok 30 menit.
        // Sisa 20 menit pada tarif 20.000/jam = 6.666 (dibulatkan ke bawah).
        $billing = $this->prepaid(0, 40);

        $this->assertSame(20, $billing['leftover_minutes']);
        $this->assertSame(6666, $billing['leftover_value']);
    }

    public function test_nilai_sisa_dibulatkan_ke_bawah(): void
    {
        // Ke bawah supaya saldo tidak pernah melebihi nilai yang benar-benar
        // tersisa — selisih yang menguntungkan customer tetap selisih.
        $this->assertSame(6666, CheckoutBilling::leftoverValue(20, 20000));
        $this->assertSame(10000, CheckoutBilling::leftoverValue(30, 20000));
        $this->assertSame(0, CheckoutBilling::leftoverValue(0, 20000));
        $this->assertSame(0, CheckoutBilling::leftoverValue(-10, 20000));
    }

    public function test_postpaid_tidak_punya_sisa_yang_bisa_disimpan(): void
    {
        // Postpaid tidak membayar di muka, jadi tidak ada yang hangus maupun
        // tersisa (DEC-024).
        $billing = $this->postpaid(0, 30);

        $this->assertSame(0, $billing['leftover_minutes']);
        $this->assertSame(0, $billing['leftover_value']);
    }

    public function test_main_tepat_sesuai_paket_tidak_ada_sisa_maupun_kelebihan(): void
    {
        $billing = $this->prepaid(0, 60);

        $this->assertSame(0, $billing['leftover_value']);
        $this->assertSame(0, $billing['overstay_price']);
        $this->assertSame(60, $billing['billable_minutes']);
    }
}
