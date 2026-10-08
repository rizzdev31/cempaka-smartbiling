<?php

namespace App\Support\Billing;

use Carbon\CarbonInterface;

/**
 * Aturan extend — DEC-007.
 *
 * | Aturan | Nilai |
 * |---|---|
 * | durasi | kelipatan 30 menit, minimum 30 |
 * | grace | boleh selama `now <= end_at + 10 menit` |
 * | `end_at` baru | `end_at lama + durasi` — **bukan** dari waktu approve |
 * | harga | `ceil(hourly_rate / 2 * (menit / 30))` |
 *
 * Kenapa `end_at` baru dihitung dari `end_at` lama, bukan dari sekarang:
 * kalau dihitung dari waktu approve, customer yang di-extend telat 8 menit
 * mendapat 8 menit gratis. Operator yang lambat jadi menentukan harga.
 */
final class ExtendPolicy
{
    /** DEC-007. Lewat ini → EXTEND_GRACE_EXPIRED. */
    public const GRACE_MINUTES = 10;

    public static function isDurationValid(int $minutes): bool
    {
        return $minutes >= DurationRounding::BLOCK_MINUTES
            && $minutes % DurationRounding::BLOCK_MINUTES === 0;
    }

    /**
     * `extend_deadline_at` di API.md §7 — dikirim server supaya aturan grace
     * tidak diduplikasi di Flutter dan Kotlin.
     */
    public static function deadlineFor(CarbonInterface $endAt): CarbonInterface
    {
        return $endAt->copy()->addMinutes(self::GRACE_MINUTES);
    }

    public static function isWithinGrace(CarbonInterface $endAt, CarbonInterface $now): bool
    {
        return $now->lessThanOrEqualTo(self::deadlineFor($endAt));
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
