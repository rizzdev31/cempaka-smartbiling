<?php

namespace App\Support\Billing;

use App\Enums\SessionMode;

/**
 * Perhitungan uang saat checkout — API.md §7, DEC-009, DEC-024, DEC-032, DEC-033.
 *
 * Murni: tidak menyentuh database, jadi bisa diuji tanpa MySQL.
 *
 * ## Tidak ada penagihan kelebihan waktu
 *
 * DEC-033 mencabut DEC-023. Waktu habis berarti TV mati dan sesi berhenti —
 * customer tidak bisa bermain melewati haknya, jadi tidak ada menit di luar hak
 * yang perlu ditagih. Seluruh cabang overstay beserta `OverstayPolicy` sudah
 * dihapus, bukan dimatikan.
 *
 * ## Kenapa rental Postpaid dikurangi menit extend (DEC-032)
 *
 * Postpaid menagih rental dari durasi AKTUAL (DEC-009), sementara extend juga
 * punya barisnya sendiri. Kalau keduanya dijumlahkan apa adanya, menit yang
 * sama tertagih dua kali: paket 1 jam + extend 30 menit + main 90 menit akan
 * jadi 90 menit rental DITAMBAH 30 menit extend.
 *
 * Penyelesaiannya: total yang ditagih tetap `rounded(aktual)`, lalu dibagi —
 * menit yang sudah ditanggung item EXTEND dikeluarkan dari rental. Jumlah
 * akhirnya sama dengan tarif per jam dikali durasi aktual.
 *
 * Prepaid tidak punya masalah ini: rental-nya harga paket yang dibekukan di
 * depan, jadi extend memang pembelian tambahan yang sah.
 */
final class CheckoutBilling
{
    /**
     * @param  int  $extendMinutes  Total menit dari semua item EXTEND.
     * @return array{
     *     rental_minutes: int, rental_price: int,
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
        if ($mode === SessionMode::POSTPAID) {
            $billable = DurationRounding::toBillableMinutes($actualMinutes);
            $rentalMinutes = max(0, $billable - $extendMinutes);

            return [
                'rental_minutes' => $rentalMinutes,
                'rental_price' => HalfHourPricing::priceFor($hourlyRate, $rentalMinutes),
                'billable_minutes' => $billable,
                // Postpaid tidak membayar di muka, jadi tidak ada sisa yang
                // bisa hangus maupun disimpan (DEC-024).
                'leftover_minutes' => 0,
                'leftover_value' => 0,
            ];
        }

        $entitled = $packageDurationMinutes + $extendMinutes;
        $leftoverMinutes = max(0, $entitled - $actualMinutes);

        return [
            'rental_minutes' => $packageDurationMinutes,
            // DEC-024: harga paket tidak pernah dihitung ulang dari durasi
            // aktual, termasuk saat customer berhenti lebih awal.
            'rental_price' => $packagePrice,
            /*
             * Seluruh hak waktu, bukan durasi aktual. Customer yang berhenti
             * lebih awal tetap membayar paket penuh, jadi yang ditagih memang
             * sepanjang itu — dan sejak DEC-033 dia tidak mungkin melebihinya.
             */
            'billable_minutes' => $entitled,
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
