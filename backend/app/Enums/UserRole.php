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

    /**
     * Permission per role — dasar `data.user.permissions` (API.md §4) dan
     * semua Gate. Diturunkan dari PRD §6.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        // PRD §6 Operator: station, session, payment, F&B, extend, swap,
        // checkout, shift. Tidak termasuk master data dan harga.
        $operator = [
            Permission::STATION_READ,
            Permission::PACKAGE_READ,
            Permission::SESSION_READ,
            Permission::SESSION_CREATE,
            Permission::SESSION_EXTEND,
            Permission::SESSION_SWAP,
            Permission::SESSION_CHECKOUT,
            Permission::SESSION_CANCEL,
            Permission::PAYMENT_CONFIRM,
            Permission::FNB_READ,
            Permission::FNB_MANAGE,
            Permission::SHIFT_MANAGE,
            Permission::CUSTOMER_READ,
            Permission::DEVICE_READ,
        ];

        // Admin = operator + master data + audit. CUSTOMER_CREATE ada di sini
        // dan bukan di operator karena OD-014 belum diputuskan.
        $admin = [...$operator, Permission::CUSTOMER_CREATE, Permission::AUDIT_READ];

        return match ($this) {
            // DEC-020: PRICING_MANAGE hanya milik owner.
            self::OWNER => [...$admin, Permission::PRICING_MANAGE],
            self::ADMIN => $admin,
            self::OPERATOR => $operator,
        };
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return list<string> */
    public function permissionValues(): array
    {
        return array_map(fn (Permission $p) => $p->value, $this->permissions());
    }
}
