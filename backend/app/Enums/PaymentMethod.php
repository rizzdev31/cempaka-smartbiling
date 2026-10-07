<?php

namespace App\Enums;

/**
 * PRD §21 — V1 hanya manual. Gateway (Midtrans) masuk Tahap 3D,
 * jadi belum ada di enum ini.
 */
enum PaymentMethod: string
{
    case CASH = 'CASH';
    case QRIS_STATIC = 'QRIS_STATIC';

    /** QRIS statis butuh nomor referensi yang diverifikasi operator (API.md §7). */
    public function requiresReference(): bool
    {
        return $this === self::QRIS_STATIC;
    }
}
