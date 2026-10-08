<?php

namespace App\Enums;

/**
 * Status session — PRD §11, API.md §7.
 *
 * `AVAILABLE` sengaja TIDAK ada di sini: itu status station, bukan session.
 * Station kosong = tidak punya session aktif.
 */
enum SessionStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case ACTIVE = 'ACTIVE';
    case WARNING = 'WARNING';
    case EXPIRED = 'EXPIRED';
    case CHECKOUT = 'CHECKOUT';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    /**
     * Transisi yang sah — PRD §11.
     *
     * Pola ini sengaja sama dengan FnbOrderStatus: satu tempat yang tahu
     * urutan status, supaya controller tidak masing-masing menebak.
     *
     * Yang perlu diperhatikan:
     *
     * - `EXPIRED` TIDAK bisa kembali hidup. DEC-033: waktu habis berarti
     *   berhenti, TV mati, dan extend tidak lagi diizinkan setelahnya.
     *   Customer yang mau lanjut dibuatkan sesi baru.
     * - `WARNING -> ACTIVE` tetap ada: extend SEBELUM waktu habis membuat sisa
     *   waktu kembali di atas ambang warning.
     * - `ACTIVE -> CHECKOUT` langsung diperbolehkan: customer berhenti lebih
     *   awal. Sisa waktunya hangus (DEC-024), bukan error.
     * - `CANCELLED` hanya dari `PENDING_PAYMENT` (API.md §7). Sesi yang sudah
     *   berjalan diselesaikan lewat checkout, bukan dibatalkan — kalau tidak,
     *   uang yang sudah masuk kehilangan jejak.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PENDING_PAYMENT => [self::ACTIVE, self::CANCELLED],
            self::ACTIVE => [self::WARNING, self::EXPIRED, self::CHECKOUT],
            self::WARNING => [self::ACTIVE, self::EXPIRED, self::CHECKOUT],
            // Satu-satunya jalan keluar dari EXPIRED adalah checkout (DEC-033).
            self::EXPIRED => [self::CHECKOUT],
            self::CHECKOUT => [self::COMPLETED],
            self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Sesi sudah selesai — tidak ada transisi keluar lagi. */
    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /**
     * Timer sedang berjalan: `end_at` sudah ada dan belum lewat.
     *
     * EXPIRED TIDAK termasuk. DEC-023 sempat memasukkannya — saat itu sesi
     * boleh lanjut dan menit yang lewat ditagih — tapi DEC-033 mencabutnya.
     */
    public function isRunning(): bool
    {
        return in_array($this, [self::ACTIVE, self::WARNING], true);
    }

    /** Status yang masih memakai station — station tidak bisa dipakai session lain. */
    public function occupiesStation(): bool
    {
        return in_array($this, [
            self::PENDING_PAYMENT,
            self::ACTIVE,
            self::WARNING,
            self::EXPIRED,
            self::CHECKOUT,
        ], true);
    }

    /** Boleh menerima order F&B (API.md §8) — ini yang menutup ghost order, T05. */
    public function isOrderable(): bool
    {
        return $this === self::ACTIVE || $this === self::WARNING;
    }

    /**
     * Boleh di-extend — sebelum pemeriksaan jam (DEC-007, diubah DEC-033).
     *
     * EXPIRED dikeluarkan: sejak DEC-033 tidak ada lagi grace 10 menit setelah
     * waktu habis.
     */
    public function isExtendable(): bool
    {
        return in_array($this, [self::ACTIVE, self::WARNING], true);
    }
}
