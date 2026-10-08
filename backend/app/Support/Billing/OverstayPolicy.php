<?php

namespace App\Support\Billing;

/**
 * Penagihan overstay — DEC-023.
 *
 * Sejak DEC-023, `EXPIRED` bukan penghenti: customer boleh terus bermain dan
 * kelebihan waktunya ditagih saat checkout. Rental Prepaid yang sudah dibayar
 * **tidak** dihitung ulang (PRD §12 — "tidak ada pembayaran rental kedua");
 * yang ditagih hanya menit di luar hak waktunya.
 *
 * ```
 * hak_waktu = durasi_paket + total_menit_extend
 * overstay  = durasi_aktual - hak_waktu
 * ```
 *
 * Pembulatan memakai DEC-009 **tanpa lantai 30 menit** — lihat OD-021.
 * Dengan lantai, kelebihan 2 menit akan ditagih setengah jam.
 */
final class OverstayPolicy
{
    public static function minutes(int $actualMinutes, int $entitledMinutes): int
    {
        return max(0, $actualMinutes - $entitledMinutes);
    }

    /**
     * Menit yang benar-benar ditagih. Nol kalau masih dalam toleransi 5 menit
     * DEC-009 — mis. lewat 4 menit tidak ditagih sama sekali.
     */
    public static function billableMinutes(int $actualMinutes, int $entitledMinutes): int
    {
        return DurationRounding::toBillableMinutes(
            self::minutes($actualMinutes, $entitledMinutes),
            applyMinimum: false,
        );
    }

    public static function priceFor(int $hourlyRate, int $actualMinutes, int $entitledMinutes): int
    {
        return HalfHourPricing::priceFor(
            $hourlyRate,
            self::billableMinutes($actualMinutes, $entitledMinutes),
        );
    }
}
