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
     * DEC-027 — operator BOLEH mendaftarkan member di kasir.
     *
     * Sebelumnya hanya admin, dan itu membuat DEC-024 jadi jalan buntu:
     * operator diminta menawarkan membership saat checkout supaya sisa waktu
     * customer bisa disimpan, tapi tidak punya tombolnya.
     */
    case CUSTOMER_CREATE = 'customer.create';

    case DEVICE_READ = 'device.read';

    /** DEC-020 — hanya OWNER. */
    case PRICING_MANAGE = 'pricing.manage';

    /** DEC-028 — hanya OWNER. Diskon adalah pengurangan harga, jadi
     *  perlakuannya sama dengan mengubah tarif. */
    case DISCOUNT_MANAGE = 'discount.manage';

    case AUDIT_READ = 'audit.read';
}
