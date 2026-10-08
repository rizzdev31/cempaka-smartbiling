<?php

namespace App\Support\Billing;

use App\Enums\SessionMode;

/**
 * Perhitungan uang saat checkout — API.md §7, DEC-009, DEC-023, DEC-024.
 *
 * Murni: tidak menyentuh database, jadi bisa diuji tanpa MySQL.
 *
 * ## Kenapa rental Postpaid dikurangi menit extend
 *
 * Postpaid menagih rental dari durasi AKTUAL (DEC-009), sementara PRD §12
 * menetapkan extend tetap masuk Open Tab sebagai item sendiri. Dua aturan itu
 * tumpang tindih: customer paket 1 jam yang extend 30 menit dan bermain 90
 * menit akan ditagih 90 menit sebagai rental **plus** 30 menit sebagai extend —
 * 30 menit dibayar dua kali.
 *
 * Penyelesaiannya: total waktu yang ditagih tetap `rounded(aktual)`, lalu
 * dibagi — menit yang sudah ditanggung item EXTEND dikeluarkan dari rental.
 * Jumlah akhirnya sama dengan tarif per jam dikali durasi aktual, dan tidak ada
 * menit yang tertagih dua kali.
 *
 * Prepaid tidak punya masalah ini: rental-nya harga paket yang dibekukan, dan
 * kelebihan waktunya ditagih lewat jalur overstay (DEC-023).
 */
final class CheckoutBilling
{
    /**
     * @param  int  $extendMinutes  Total menit dari semua item EXTEND.
     * @return array{
     *     rental_minutes: int, rental_price: int,
     *     overstay_minutes: int, overstay_price: int,
     *     billable_minutes: int, leftover_minutes: int, leftover_value: int
     * }
     */
    public static function compute(
        SessionMode $mode,
        int $packageDurationMinutes,
        int $packagePrice,
        int $hourlyRate,
        int $extendMinutes,
        int $actualMinutes,
    ): array {
        $entitled = $packageDurationMinutes + $extendMinutes;

        if ($mode === SessionMode::POSTPAID) {
            $billable = DurationRounding::toBillableMinutes($actualMinutes);
            $rentalMinutes = max(0, $billable - $extendMinutes);

            return [
                'rental_minutes' => $rentalMinutes,
                'rental_price' => HalfHourPricing::priceFor($hourlyRate, $rentalMinutes),
                // Postpaid menagih durasi aktual, jadi tidak ada "kelebihan"
                // yang belum tertagih.
                'overstay_minutes' => 0,
                'overstay_price' => 0,
                'billable_minutes' => $billable,
                // Postpaid tidak membayar di muka, jadi tidak ada sisa yang
                // bisa hangus maupun disimpan (DEC-024).
                'leftover_minutes' => 0,
                'leftover_value' => 0,
            ];
        }

        $overstayMinutes = OverstayPolicy::billableMinutes($actualMinutes, $entitled);
        $leftoverMinutes = max(0, $entitled - $actualMinutes);

        return [
            'rental_minutes' => $packageDurationMinutes,
            // DEC-024: harga paket tidak pernah dihitung ulang dari durasi
            // aktual, termasuk saat customer berhenti lebih awal.
            'rental_price' => $packagePrice,
            'overstay_minutes' => $overstayMinutes,
            'overstay_price' => HalfHourPricing::priceFor($hourlyRate, $overstayMinutes),
            'billable_minutes' => $entitled + $overstayMinutes,
            'leftover_minutes' => $leftoverMinutes,
            'leftover_value' => self::leftoverValue($leftoverMinutes, $hourlyRate),
        ];
    }

    /**
     * Nilai sisa waktu member — DEC-026.
     *
     * Proporsional per menit, **tidak** dibulatkan ke blok 30 menit: user
     * menetapkan "sisa waktu berapapun akan disimpan". Dibulatkan ke BAWAH
     * supaya saldo tidak pernah melebihi nilai yang benar-benar tersisa.
     */
    public static function leftoverValue(int $leftoverMinutes, int $hourlyRate): int
    {
        if ($leftoverMinutes <= 0) {
            return 0;
        }

        return (int) floor($leftoverMinutes * $hourlyRate / 60);
    }
}
