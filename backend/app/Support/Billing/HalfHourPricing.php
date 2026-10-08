<?php

namespace App\Support\Billing;

/**
 * Harga per blok 30 menit — dasar rumus extend DEC-007:
 *
 * ```
 * harga = ceil(hourly_rate / 2 * (menit / 30))
 * ```
 *
 * Dipakai extend dan rental Postpaid, supaya satu blok 30 menit berharga sama
 * lewat jalur mana pun. Kalau masing-masing punya rumusnya sendiri, durasi yang
 * sama bisa beda tagihan dan operator tidak akan bisa menjelaskannya ke customer.
 *
 * Dibulatkan ke ATAS: tarif per jam ganjil (mis. 21.667 dari paket 3 jam)
 * kalau dibulatkan ke bawah membuat rental kehilangan rupiah di setiap extend.
 */
final class HalfHourPricing
{
    public static function priceFor(int $hourlyRate, int $minutes): int
    {
        if ($minutes <= 0) {
            return 0;
        }

        return (int) ceil($hourlyRate / 2 * ($minutes / DurationRounding::BLOCK_MINUTES));
    }
}
