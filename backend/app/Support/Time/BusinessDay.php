<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Batas "hari" menurut rental, bukan menurut UTC.
 *
 * ## Masalah yang diselesaikan
 *
 * DEC-005 mewajibkan semua timestamp disimpan UTC. Itu benar, tapi punya
 * akibat yang mudah terlewat: `whereDate('confirmed_at', today())` memotong
 * hari pada **00:00 UTC = 07:00 WIB**.
 *
 * Untuk rental PlayStation, itu jam yang paling salah untuk dijadikan batas.
 * Sesi yang berjalan tengah malam sampai subuh — jam paling ramai — akan
 * tercatat di hari sebelumnya, dan laporan harian tidak akan pernah cocok
 * dengan isi laci kas. Kesalahannya pun tidak kelihatan: angkanya tetap
 * masuk akal, hanya di kolom yang salah.
 *
 * Kelas ini selalu mengembalikan rentang dalam **UTC**, siap dipakai
 * langsung di query, tapi batasnya dihitung di zona waktu rental.
 *
 * ## Yang BELUM diputuskan
 *
 * Hari dipotong pada tengah malam WIB. Kalau rental tutup jam 02:00, sesi
 * yang selesai jam 01:30 masuk ke hari berikutnya — padahal bagi operator
 * itu masih shift yang sama. Pertanyaan "hari usaha berakhir jam berapa"
 * belum pernah diajukan ke user, dan jawabannya hanya mengubah kelas ini.
 *
 * Untuk pertanyaan "berapa yang saya terima saat jaga", `Shift` sudah
 * menjawabnya dengan benar tanpa bergantung pada batas hari sama sekali.
 */
final class BusinessDay
{
    public static function timezone(): string
    {
        return config('app.display_timezone', 'Asia/Jakarta');
    }

    /** "Sekarang" menurut jam dinding rental. */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /**
     * Rentang satu hari penuh, dalam UTC.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [mulai, selesai)
     */
    public static function range(?CarbonInterface $day = null): array
    {
        $local = $day
            ? CarbonImmutable::parse($day)->setTimezone(self::timezone())
            : self::now();

        return [
            $local->startOfDay()->utc(),
            // Eksklusif: pakai `>= mulai AND < selesai`, bukan `between`.
            // `between` dengan 23:59:59 melewatkan transaksi di detik
            // terakhir — jarang, tapi selalu di saat tersibuk.
            $local->addDay()->startOfDay()->utc(),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function today(): array
    {
        return self::range();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function yesterday(): array
    {
        return self::range(self::now()->subDay());
    }

    /**
     * [$jumlah] hari terakhir, paling lama di depan, hari ini di belakang.
     *
     * @return list<array{label: string, date: CarbonImmutable, from: CarbonImmutable, to: CarbonImmutable}>
     */
    public static function lastDays(int $count): array
    {
        $out = [];

        for ($i = $count - 1; $i >= 0; $i--) {
            $day = self::now()->subDays($i);
            [$from, $to] = self::range($day);

            $out[] = [
                // `translatedFormat` supaya "Sen/Sel/Rab" ikut locale app,
                // bukan "Mon/Tue/Wed" di antarmuka berbahasa Indonesia.
                'label' => $day->translatedFormat('D'),
                'date' => $day,
                'from' => $from,
                'to' => $to,
            ];
        }

        return $out;
    }
}
