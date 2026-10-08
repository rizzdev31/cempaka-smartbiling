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
 * Hanya berlaku untuk **Postpaid** dan untuk overstay (DEC-023).
 * Prepaid memakai durasi paket apa adanya, dan extend selalu kelipatan 30
 * sejak awal — keduanya tidak pernah lewat sini.
 */
final class DurationRounding
{
    public const BLOCK_MINUTES = 30;

    /** Toleransi DEC-009: lebihan sampai 5 menit dianggap tidak terjadi. */
    public const TOLERANCE_MINUTES = 5;

    /**
     * @param  bool  $applyMinimum  Lantai 30 menit (DEC-009). Dimatikan untuk
     *                              overstay: kelebihan 2 menit tidak boleh
     *                              langsung ditagih setengah jam (DEC-023,
     *                              asumsi OD-021).
     */
    public static function toBillableMinutes(int $minutes, bool $applyMinimum = true): int
    {
        if ($minutes <= 0) {
            return $applyMinimum ? self::BLOCK_MINUTES : 0;
        }

        $remainder = $minutes % self::BLOCK_MINUTES;

        $billable = $remainder <= self::TOLERANCE_MINUTES
            ? $minutes - $remainder
            : $minutes - $remainder + self::BLOCK_MINUTES;

        return $applyMinimum
            ? max($billable, self::BLOCK_MINUTES)
            : $billable;
    }

    /** Jumlah blok 30 menit yang ditagih. Dasar perhitungan harga. */
    public static function toBlocks(int $minutes, bool $applyMinimum = true): int
    {
        return intdiv(self::toBillableMinutes($minutes, $applyMinimum), self::BLOCK_MINUTES);
    }
}
