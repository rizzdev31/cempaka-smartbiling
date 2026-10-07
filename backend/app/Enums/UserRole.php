<?php

namespace App\Enums;

/**
 * Tiga role (DEC-020). PRD §6 menulis "Admin / Owner" sebagai satu aktor;
 * DEC-020 memisahkannya khusus untuk harga.
 */
enum UserRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case OPERATOR = 'OPERATOR';

    /**
     * Hanya OWNER yang boleh mengubah tarif/paket (DEC-020).
     * Ditegakkan server — menyembunyikan tombol di Flutter bukan kontrol keamanan.
     */
    public function canManagePricing(): bool
    {
        return $this === self::OWNER;
    }

    /** Owner punya semua akses admin. */
    public function isAdminLevel(): bool
    {
        return $this === self::OWNER || $this === self::ADMIN;
    }
}
