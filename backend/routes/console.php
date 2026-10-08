<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Reconciliation status sesi — ROADMAP Tahap 0.
 *
 * Tiap menit, bukan tiap detik. `session.expired` memang boleh terlambat
 * beberapa detik (REALTIME.md §5): tampilan "habis" di tablet dan TV
 * ditentukan timer lokal dari `end_at`, bukan event ini. Menjalankannya tiap
 * detik hanya menambah beban tanpa mengubah apa yang dilihat operator.
 *
 * `withoutOverlapping` supaya dua proses tidak memindahkan status sesi yang
 * sama bersamaan saat satu putaran kebetulan lambat.
 *
 * Dijalankan dengan `php artisan schedule:work` selama Tahap 0 (DEC-002 —
 * semuanya lokal; cron baru dipasang saat migrasi VPS di Tahap 3A).
 */
Schedule::command('sessions:reconcile')
    ->everyMinute()
    ->withoutOverlapping();
