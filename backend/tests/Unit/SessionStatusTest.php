<?php

namespace Tests\Unit;

use App\Enums\FnbOrderStatus;
use App\Enums\SessionStatus;
use PHPUnit\Framework\TestCase;

/** Aturan status — PRD §11, API.md §7/§8. */
class SessionStatusTest extends TestCase
{
    public function test_available_bukan_status_session(): void
    {
        // AVAILABLE adalah status station. Kalau ikut masuk ke status session,
        // station kosong jadi punya dua sumber kebenaran.
        $values = array_map(fn (SessionStatus $s) => $s->value, SessionStatus::cases());

        $this->assertNotContains('AVAILABLE', $values);
    }

    public function test_hanya_active_dan_warning_boleh_menerima_order_fnb(): void
    {
        // Ini yang menutup ghost order (T05).
        $this->assertTrue(SessionStatus::ACTIVE->isOrderable());
        $this->assertTrue(SessionStatus::WARNING->isOrderable());

        $this->assertFalse(SessionStatus::PENDING_PAYMENT->isOrderable());
        $this->assertFalse(SessionStatus::EXPIRED->isOrderable());
        $this->assertFalse(SessionStatus::COMPLETED->isOrderable());
        $this->assertFalse(SessionStatus::CANCELLED->isOrderable());
    }

    public function test_expired_masih_boleh_diextend_karena_ada_grace(): void
    {
        // DEC-007 memberi grace 10 menit setelah end_at, jadi EXPIRED belum
        // menutup pintu extend. Batas waktunya diuji terpisah di langkah 9.
        $this->assertTrue(SessionStatus::EXPIRED->isExtendable());
        $this->assertFalse(SessionStatus::COMPLETED->isExtendable());
        $this->assertFalse(SessionStatus::PENDING_PAYMENT->isExtendable());
    }

    public function test_session_selesai_tidak_lagi_memakai_station(): void
    {
        $this->assertTrue(SessionStatus::PENDING_PAYMENT->occupiesStation());
        $this->assertTrue(SessionStatus::ACTIVE->occupiesStation());
        $this->assertTrue(SessionStatus::EXPIRED->occupiesStation());

        $this->assertFalse(SessionStatus::COMPLETED->occupiesStation());
        $this->assertFalse(SessionStatus::CANCELLED->occupiesStation());
    }

    public function test_transisi_status_fnb_mengikuti_kontrak(): void
    {
        $this->assertTrue(FnbOrderStatus::PENDING->canTransitionTo(FnbOrderStatus::PROCESSING));
        $this->assertTrue(FnbOrderStatus::PROCESSING->canTransitionTo(FnbOrderStatus::READY));
        $this->assertTrue(FnbOrderStatus::READY->canTransitionTo(FnbOrderStatus::DELIVERED));

        // Tidak boleh melompat.
        $this->assertFalse(FnbOrderStatus::PENDING->canTransitionTo(FnbOrderStatus::DELIVERED));

        // Cancel hanya dari PENDING atau PROCESSING.
        $this->assertTrue(FnbOrderStatus::PENDING->canTransitionTo(FnbOrderStatus::CANCELLED));
        $this->assertTrue(FnbOrderStatus::PROCESSING->canTransitionTo(FnbOrderStatus::CANCELLED));
        $this->assertFalse(FnbOrderStatus::READY->canTransitionTo(FnbOrderStatus::CANCELLED));

        // DELIVERED final.
        $this->assertSame([], FnbOrderStatus::DELIVERED->allowedNext());
    }
}
