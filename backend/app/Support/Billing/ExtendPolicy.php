<?php

namespace App\Support\Billing;

use Carbon\CarbonInterface;

/**
 * Aturan extend — DEC-007, sebagaimana diubah DEC-033.
 *
 * | Aturan | Nilai |
 * |---|---|
 * | durasi | kelipatan 30 menit, minimum 30 |
 * | kapan boleh | hanya **sebelum** waktu habis: `now ≤ end_at` |
 * | `end_at` baru | `end_at lama + durasi` |
 * | harga | `ceil(hourly_rate / 2 * (menit / 30))` |
 *
 * **DEC-033 mencabut grace 10 menit.** Dulu extend masih boleh sampai 10 menit
 * setelah waktu habis; sekarang tidak — "habis ya habis". Customer yang ingin
 * melanjutkan dibuatkan **sesi baru dengan paket baru**.
 *
 * Yang TIDAK berubah: `end_at` baru tetap dihitung dari `end_at` lama, bukan
 * dari waktu approve. Operator yang menekan tombol 2 menit sebelum habis tidak
 * boleh memberi 2 menit gratis.
 */
final class ExtendPolicy
{
    public static function isDurationValid(int $minutes): bool
    {
        return $minutes >= DurationRounding::BLOCK_MINUTES
            && $minutes % DurationRounding::BLOCK_MINUTES === 0;
    }

    /**
     * `extend_deadline_at` di API.md §7.
     *
     * Sejak DEC-033 nilainya sama persis dengan `end_at`. Field-nya sengaja
     * TIDAK dihapus dari kontrak supaya client yang sudah membacanya tidak
     * patah; yang berubah hanya angkanya.
     */
    public static function deadlineFor(CarbonInterface $endAt): CarbonInterface
    {
        return $endAt->copy();
    }

    /** Boleh extend selama waktunya belum lewat. Tepat di `end_at` masih boleh. */
    public static function isWithinWindow(CarbonInterface $endAt, CarbonInterface $now): bool
    {
        return $now->lessThanOrEqualTo($endAt);
    }

    public static function newEndAt(CarbonInterface $endAt, int $minutes): CarbonInterface
    {
        return $endAt->copy()->addMinutes($minutes);
    }

    public static function priceFor(int $hourlyRate, int $minutes): int
    {
        return HalfHourPricing::priceFor($hourlyRate, $minutes);
    }
}
