<?php

use App\Enums\Permission;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Channel realtime — REALTIME.md §3.
 *
 * Semua channel PRIVATE. Tidak ada channel publik: nama station dan nominal
 * tagihan tidak boleh bisa didengarkan siapa pun yang tahu nama channel-nya.
 */

/**
 * Semua event operasional lokasi. Subscriber: Flutter Operator dan Admin Web.
 *
 * Dibatasi permission, bukan sekadar "sudah login": akun yang dinonaktifkan
 * kehilangan `session.read` lewat Gate, jadi ikut kehilangan channel ini.
 */
Broadcast::channel('operator', function (User $user) {
    return $user->is_active && $user->role->hasPermission(Permission::SESSION_READ);
});

/**
 * Satu channel per station, dinamai `station_code` (`ST01`) dan bukan UUID
 * supaya mudah dibaca saat debugging di lapangan (REALTIME.md §3).
 *
 * Subscriber yang dituju adalah Kotlin TV Agent dengan `X-Device-Token`.
 * Guard device-nya baru ada di Tahap 2 bersama `POST /devices/register` —
 * sampai itu ada, yang bisa subscribe hanya user ber-token Sanctum.
 * TV belum bisa subscribe; itu disengaja, bukan terlewat.
 */
Broadcast::channel('station.{code}', function (User $user, string $code) {
    if (! $user->is_active || ! $user->role->hasPermission(Permission::STATION_READ)) {
        return false;
    }

    return Station::query()->where('code', $code)->exists();
});

/**
 * Customer Portal — Tahap 3C. Didaftarkan sekarang hanya supaya namanya tidak
 * berubah nanti (REALTIME.md §3). Selalu ditolak di v1.
 */
Broadcast::channel('session.{id}', fn () => false);
