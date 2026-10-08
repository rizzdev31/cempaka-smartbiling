<?php

namespace Tests\Unit;

use App\Enums\SessionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Mesin state session — PRD §11, DEC-023.
 *
 * Satu test per transisi yang penting, termasuk transisi yang HARUS ditolak.
 * Yang ditolak lebih berharga daripada yang diterima: itu yang mencegah sesi
 * selesai melompati pembayaran.
 */
class SessionTransitionTest extends TestCase
{
    public function test_prepaid_menunggu_bayar_lalu_aktif(): void
    {
        $this->assertTrue(SessionStatus::PENDING_PAYMENT->canTransitionTo(SessionStatus::ACTIVE));
    }

    public function test_pending_payment_boleh_dibatalkan(): void
    {
        $this->assertTrue(SessionStatus::PENDING_PAYMENT->canTransitionTo(SessionStatus::CANCELLED));
    }

    public function test_sesi_berjalan_tidak_boleh_dibatalkan(): void
    {
        // API.md §7: cancel hanya dari PENDING_PAYMENT di v1. Sesi yang sudah
        // jalan diselesaikan lewat checkout — kalau boleh di-cancel, uang yang
        // sudah masuk kehilangan jejak.
        $this->assertFalse(SessionStatus::ACTIVE->canTransitionTo(SessionStatus::CANCELLED));
        $this->assertFalse(SessionStatus::EXPIRED->canTransitionTo(SessionStatus::CANCELLED));
        $this->assertFalse(SessionStatus::CHECKOUT->canTransitionTo(SessionStatus::CANCELLED));
    }

    public function test_tidak_boleh_melompat_dari_pending_payment_ke_selesai(): void
    {
        // Ini yang mencegah sesi selesai tanpa pernah dibayar.
        $this->assertFalse(SessionStatus::PENDING_PAYMENT->canTransitionTo(SessionStatus::COMPLETED));
        $this->assertFalse(SessionStatus::PENDING_PAYMENT->canTransitionTo(SessionStatus::CHECKOUT));
        $this->assertFalse(SessionStatus::PENDING_PAYMENT->canTransitionTo(SessionStatus::EXPIRED));
    }

    public function test_extend_mengembalikan_sesi_dari_warning(): void
    {
        // Extend SEBELUM waktu habis menggeser end_at ke depan, jadi sisa waktu
        // kembali di atas ambang warning.
        $this->assertTrue(SessionStatus::WARNING->canTransitionTo(SessionStatus::ACTIVE));
    }

    public function test_sesi_yang_sudah_habis_tidak_bisa_hidup_lagi(): void
    {
        // DEC-033 mencabut DEC-023. Waktu habis berarti berhenti; satu-satunya
        // jalan keluar dari EXPIRED adalah checkout.
        $this->assertFalse(SessionStatus::EXPIRED->canTransitionTo(SessionStatus::ACTIVE));
        $this->assertFalse(SessionStatus::EXPIRED->canTransitionTo(SessionStatus::WARNING));
        $this->assertSame([SessionStatus::CHECKOUT], SessionStatus::EXPIRED->allowedNext());
    }

    public function test_berhenti_lebih_awal_langsung_ke_checkout(): void
    {
        // DEC-024: customer berhenti sebelum waktunya habis. Sisa hangus,
        // bukan error.
        $this->assertTrue(SessionStatus::ACTIVE->canTransitionTo(SessionStatus::CHECKOUT));
    }

    public function test_hanya_checkout_yang_boleh_menyelesaikan_sesi(): void
    {
        $this->assertTrue(SessionStatus::CHECKOUT->canTransitionTo(SessionStatus::COMPLETED));

        $this->assertFalse(SessionStatus::ACTIVE->canTransitionTo(SessionStatus::COMPLETED));
        $this->assertFalse(SessionStatus::EXPIRED->canTransitionTo(SessionStatus::COMPLETED));
    }

    public function test_status_akhir_tidak_punya_transisi_keluar(): void
    {
        $this->assertSame([], SessionStatus::COMPLETED->allowedNext());
        $this->assertSame([], SessionStatus::CANCELLED->allowedNext());

        $this->assertTrue(SessionStatus::COMPLETED->isFinal());
        $this->assertTrue(SessionStatus::CANCELLED->isFinal());
        $this->assertFalse(SessionStatus::ACTIVE->isFinal());
    }

    public function test_expired_berhenti_tapi_station_masih_terpakai(): void
    {
        // DEC-033: timer berhenti dan TV mati. Tapi station belum kosong —
        // customer masih di sana dan tagihannya belum ditutup.
        $this->assertFalse(SessionStatus::EXPIRED->isRunning());
        $this->assertTrue(SessionStatus::EXPIRED->occupiesStation());
        $this->assertFalse(SessionStatus::EXPIRED->isFinal());
    }

    public function test_pending_payment_belum_menjalankan_timer(): void
    {
        // end_at baru diisi setelah payment (API.md §7).
        $this->assertFalse(SessionStatus::PENDING_PAYMENT->isRunning());
    }

    public function test_tidak_ada_transisi_ke_diri_sendiri(): void
    {
        // Mencegah endpoint "mengaktifkan" sesi yang sudah aktif dan membuat
        // started_at tertimpa.
        foreach (SessionStatus::cases() as $status) {
            $this->assertFalse(
                $status->canTransitionTo($status),
                "{$status->value} tidak boleh transisi ke dirinya sendiri",
            );
        }
    }
}
