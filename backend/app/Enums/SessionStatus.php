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

    /** Boleh di-extend sebelum cek grace 10 menit (DEC-007). */
    public function isExtendable(): bool
    {
        return in_array($this, [self::ACTIVE, self::WARNING, self::EXPIRED], true);
    }
}
