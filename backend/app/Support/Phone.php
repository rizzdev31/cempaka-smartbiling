<?php

namespace App\Support;

/**
 * Nomor telepon dalam bentuk yang bisa dipakai tautan `wa.me` — DEC-035.
 *
 * Operator mengetik nomor seperti yang diucapkan customer: `0812-3456-7890`.
 * Tautan WhatsApp menolak bentuk itu — ia butuh kode negara tanpa nol dan
 * tanpa tanda baca: `6281234567890`.
 *
 * Diubah di server, bukan di Flutter, karena aturannya akan dibutuhkan juga
 * oleh Admin Web nanti (Tahap 3B). Satu tempat yang tahu caranya, bukan dua
 * yang bisa berbeda.
 *
 * Yang disimpan di database tetap apa adanya seperti yang diketik operator —
 * nomor yang sudah "dirapikan" membuat pencarian gagal saat operator mengetik
 * ulang persis seperti yang dia ketik dulu.
 */
final class Phone
{
    /** Kode negara Indonesia. */
    private const COUNTRY_CODE = '62';

    /**
     * Nomor Indonesia terpendek yang masuk akal (mis. 0812 3456 789) punya
     * 11 digit; di bawah 9 hampir pasti salah ketik. Lebih baik mengembalikan
     * null daripada menghasilkan tautan yang membuka obrolan ke nomor asing.
     */
    private const MIN_DIGITS = 9;

    public static function toWhatsApp(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < self::MIN_DIGITS) {
            return null;
        }

        // 0812... → 62812...
        if (str_starts_with($digits, '0')) {
            return self::COUNTRY_CODE.substr($digits, 1);
        }

        // Sudah berkode negara, dari input "+62..." maupun "62...".
        if (str_starts_with($digits, self::COUNTRY_CODE)) {
            return $digits;
        }

        // 812... — operator melewatkan nolnya.
        if (str_starts_with($digits, '8')) {
            return self::COUNTRY_CODE.$digits;
        }

        /*
         * Bukan pola Indonesia. Dikembalikan apa adanya, bukan dipaksa diberi
         * kode 62: customer asing dengan nomor negara lain lebih baik
         * menghasilkan tautan yang mungkin benar daripada pasti salah.
         */
        return $digits;
    }
}
