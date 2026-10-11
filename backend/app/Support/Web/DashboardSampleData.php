<?php

namespace App\Support\Web;

/**
 * ⚠️ DATA CONTOH — BUKAN DARI DATABASE. ⚠️
 *
 * Satu-satunya tugas kelas ini adalah memberi angka supaya tata letak
 * dashboard bisa dinilai sebelum query-nya ditulis.
 *
 * ## Kenapa ini ditandai keras-keras
 *
 * Aplikasi operator Flutter sudah pernah kena: `FakeBillingRepository`
 * terlihat begitu meyakinkan sehingga berbulan-bulan dikira sudah
 * tersambung ke server, dan beberapa aturan bisnis yang "sudah jalan"
 * ternyata hanya hidup di data palsu. Pelajarannya ditulis di
 * `docs/PROGRESS.md`.
 *
 * Karena itu:
 * - namanya mengandung "Sample", bukan "DashboardData";
 * - halamannya menampilkan spanduk peringatan yang tidak bisa dimatikan;
 * - angkanya memakai tarif asli (DEC-036) supaya nominalnya masuk akal, tapi
 *   tidak ada satu pun yang berasal dari tabel.
 *
 * ## Saat menggantinya dengan data nyata
 *
 * Ganti kelas ini, jangan tambahkan query di sampingnya. Bentuk arraynya
 * sengaja dibuat sama dengan yang nanti dikembalikan query, jadi view tidak
 * perlu ikut berubah — dan spanduk peringatannya ikut hilang bersama kelas
 * ini, bukan dilupakan menyala.
 */
final class DashboardSampleData
{
    /** @return array<string, mixed> */
    public static function all(): array
    {
        return [
            'generated_at' => now(),

            // Semua uang integer rupiah, tanpa desimal (CLAUDE.md §4).
            'revenue_today' => 1_285_000,
            'revenue_yesterday' => 1_140_000,

            'sessions_active' => 4,
            'stations_total' => 6,

            'fnb_open' => 3,
            'members_new' => 2,

            'occupancy_percent' => 66.7,
            'sessions_done_today' => 17,
            'avg_minutes' => 94,

            /*
             * Tujuh hari, hari ini paling kanan dan ditandai aktif — pola
             * "kolom aktif + tooltip" dari referensi.
             */
            'revenue_by_day' => [
                ['label' => 'Sen', 'value' => 820_000],
                ['label' => 'Sel', 'value' => 610_000],
                ['label' => 'Rab' , 'value' => 740_000],
                ['label' => 'Kam', 'value' => 980_000],
                ['label' => 'Jum', 'value' => 1_460_000],
                ['label' => 'Sab', 'value' => 1_720_000],
                ['label' => 'Min', 'value' => 1_285_000, 'active' => true],
            ],

            /*
             * Status memakai nilai enum `SessionStatus` yang asli, bukan teks
             * bebas. Kalau nanti ada status baru di backend, `x-status-pill`
             * akan menampilkannya mentah dengan nada waspada — terlihat,
             * bukan lolos diam-diam.
             */
            'recent_sessions' => [
                ['station' => 'ST01', 'console' => 'PS4', 'customer' => 'Budi',    'package' => '3 Jam + 1 Gratis', 'started' => '18:10', 'status' => 'ACTIVE',          'minutes' => 240, 'total' => 35_000],
                ['station' => 'ST03', 'console' => 'PS4', 'customer' => 'Walk-in', 'package' => '1 Jam',            'started' => '19:25', 'status' => 'WARNING',         'minutes' => 60,  'total' => 10_000],
                ['station' => 'ST02', 'console' => 'PS3', 'customer' => 'Rina',    'package' => 'Open Tab',         'started' => '19:40', 'status' => 'ACTIVE',          'minutes' => 0,   'total' => 12_000],
                ['station' => 'ST05', 'console' => 'PS3', 'customer' => 'Agus',    'package' => '3 Jam Sepi',       'started' => '16:00', 'status' => 'COMPLETED',       'minutes' => 180, 'total' => 30_000],
                ['station' => 'ST04', 'console' => 'PS4', 'customer' => 'Dewi',    'package' => '1 Jam',            'started' => '20:05', 'status' => 'PENDING_PAYMENT', 'minutes' => 60,  'total' => 10_000],
                ['station' => 'ST06', 'console' => 'PS4', 'customer' => 'Fajar',   'package' => '1 Jam',            'started' => '15:30', 'status' => 'CANCELLED',       'minutes' => 0,   'total' => 0],
            ],
        ];
    }

    /** Selisih persen, dibulatkan satu desimal. Pembagi nol dianggap 0. */
    public static function delta(int $now, int $before): float
    {
        return $before === 0 ? 0.0 : round((($now - $before) / $before) * 100, 1);
    }
}
