<?php

namespace App\Support\Billing;

/**
 * Pembulatan durasi — DEC-009.
 *
 * ```
 * sisa = menit mod 30
 * sisa <= 5  -> dibulatkan ke bawah
 * sisa >  5  -> dibulatkan ke atas
 * minimum 30 menit
 * ```
 *
 * Hanya berlaku untuk **Postpaid**. Prepaid memakai durasi paket apa adanya,
 * dan extend selalu kelipatan 30 sejak awal — keduanya tidak pernah lewat sini.
 *
 * Dulu dipakai juga oleh penagihan overstay (DEC-023) dengan lantai 30 menit
 * dimatikan. DEC-033 menghapus jalur itu, jadi sakelarnya ikut dibuang —
 * parameter yang tidak dipakai siapa pun hanya mengundang pemakaian yang salah.
 */
final class DurationRounding
{
    public const BLOCK_MINUTES = 30;

    /** Toleransi DEC-009: lebihan sampai 5 menit dianggap tidak terjadi. */
    public const TOLERANCE_MINUTES = 5;

    public static function toBillableMinutes(int $minutes): int
    {
        // Main 3 menit tetap ditagih setengah jam: station sudah dipakai dan
        // tidak bisa dijual ke orang lain selama itu.
        if ($minutes <= 0) {
            return self::BLOCK_MINUTES;
        }

        $remainder = $minutes % self::BLOCK_MINUTES;

        $billable = $remainder <= self::TOLERANCE_MINUTES
            ? $minutes - $remainder
            : $minutes - $remainder + self::BLOCK_MINUTES;

        return max($billable, self::BLOCK_MINUTES);
    }

    /** Jumlah blok 30 menit yang ditagih. Dasar perhitungan harga. */
    public static function toBlocks(int $minutes): int
    {
        return intdiv(self::toBillableMinutes($minutes), self::BLOCK_MINUTES);
    }
}
