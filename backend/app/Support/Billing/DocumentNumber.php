<?php

namespace App\Support\Billing;

use App\Models\BillingSession;
use Illuminate\Support\Carbon;

/**
 * Nomor session (`S-20261002-0001`) dan nomor struk (`INV-20261002-0001`)
 * sesuai contoh di API.md §7.
 *
 * Nomor urut dihitung dari jumlah baris hari itu. Ini bisa bertabrakan kalau
 * dua session dibuat pada saat yang sama — ditangani dengan percobaan ulang,
 * bukan dengan tabel counter: DEC-013 menetapkan satu tablet kasir per lokasi,
 * jadi tabrakan praktis hanya terjadi saat operator menekan tombol dua kali,
 * dan itu sudah dicegat Idempotency-Key lebih dulu.
 *
 * Kolom `code` dan `receipt_number` unik di database — kalau asumsi ini salah,
 * yang terjadi adalah error, bukan dua sesi bernomor sama.
 */
final class DocumentNumber
{
    public static function sessionCode(?Carbon $now = null): string
    {
        return self::next('S', 'code', $now);
    }

    public static function receiptNumber(?Carbon $now = null): string
    {
        return self::next('INV', 'receipt_number', $now);
    }

    private static function next(string $prefix, string $column, ?Carbon $now): string
    {
        $now ??= Carbon::now();
        $day = $now->format('Ymd');

        $used = BillingSession::query()
            ->where($column, 'like', "{$prefix}-{$day}-%")
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $day, $used + 1);
    }
}
