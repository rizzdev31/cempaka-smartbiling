<?php

use App\Enums\Permission;
use App\Models\Device;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Channel realtime — REALTIME.md §3.
 *
 * Semua channel PRIVATE. Tidak ada channel publik: nama station dan nominal
 * tagihan tidak boleh bisa didengarkan siapa pun yang tahu nama channel-nya.
 *
 * Yang masuk ke callback bisa User (token Sanctum) atau Device (header
 * X-Device-Token) — `POST /broadcasting/auth` menerima kedua guard.
 */

/**
 * Semua event operasional lokasi. Subscriber: Flutter Operator dan Admin Web.
 *
 * Device TIDAK boleh masuk sini. Channel ini membawa nominal pembayaran dan
 * seluruh Open Tab; TV tidak punya urusan dengan itu (PRD §6).
 */
Broadcast::channel('operator', function ($pengguna) {
    if (! $pengguna instanceof User) {
        return false;
    }

    return $pengguna->is_active && $pengguna->role->hasPermission(Permission::SESSION_READ);
});

/**
 * Satu channel per station, dinamai `station_code` (`ST01`) dan bukan UUID
 * supaya mudah dibaca saat debugging di lapangan (REALTIME.md §3).
 */
Broadcast::channel('station.{code}', function ($pengguna, string $code) {
    /*
     * TV hanya boleh mendengarkan station yang dipetakan ke dirinya
     * (REALTIME.md §3, PRD §24). Tanpa pemeriksaan ini, satu token TV bisa
     * memantau seluruh station — termasuk melihat nama customer di station
     * lain.
     */
    if ($pengguna instanceof Device) {
        return ! $pengguna->isRevoked() && $pengguna->station?->code === $code;
    }

    if (! $pengguna instanceof User) {
        return false;
    }

    if (! $pengguna->is_active || ! $pengguna->role->hasPermission(Permission::STATION_READ)) {
        return false;
    }

    return Station::query()->where('code', $code)->exists();
});

/**
 * Customer Portal — Tahap 3C. Didaftarkan sekarang hanya supaya namanya tidak
 * berubah nanti (REALTIME.md §3). Selalu ditolak di v1.
 */
Broadcast::channel('session.{id}', fn () => false);
