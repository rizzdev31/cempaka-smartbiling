<?php

namespace App\Enums;

/**
 * Permission yang dikirim ke client di `data.user.permissions` (API.md §4).
 *
 * Tidak ada tabel permission (PRD §22 tidak punya) — permission diturunkan
 * dari role, lihat UserRole::permissions(). Konsekuensinya: mengubah role
 * langsung mengubah permission tanpa perlu login ulang.
 *
 * Client memakai daftar ini hanya untuk MENYEMBUNYIKAN tombol. Penolakan
 * sebenarnya tetap di server (PRD §24) — daftar ini bukan kontrol keamanan.
 */
enum Permission: string
{
    case STATION_READ = 'station.read';
    case PACKAGE_READ = 'package.read';

    case SESSION_READ = 'session.read';
    case SESSION_CREATE = 'session.create';
    case SESSION_EXTEND = 'session.extend';
    case SESSION_SWAP = 'session.swap';
    case SESSION_CHECKOUT = 'session.checkout';
    case SESSION_CANCEL = 'session.cancel';

    case PAYMENT_CONFIRM = 'payment.confirm';

    case FNB_READ = 'fnb.read';
    case FNB_MANAGE = 'fnb.manage';

    case SHIFT_MANAGE = 'shift.manage';

    case CUSTOMER_READ = 'customer.read';

    /**
     * Operator TIDAK punya ini — OD-014 belum diputuskan (bolehkah operator
     * mendaftarkan member baru di meja kasir?). Sampai diputuskan, customer
     * yang belum terdaftar dilayani sebagai Walk-in.
     */
    case CUSTOMER_CREATE = 'customer.create';

    case DEVICE_READ = 'device.read';

    /** DEC-020 — hanya OWNER. */
    case PRICING_MANAGE = 'pricing.manage';

    case AUDIT_READ = 'audit.read';
}
