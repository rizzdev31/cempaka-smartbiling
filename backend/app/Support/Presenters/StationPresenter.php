<?php

namespace App\Support\Presenters;

use App\Models\BillingSession;
use App\Models\Device;
use App\Models\Station;

/**
 * Objek `station` untuk dashboard — API.md §6.
 *
 * `session` null berarti station kosong. Tidak ada status `AVAILABLE` yang
 * disimpan di mana pun: station kosong adalah station yang tidak punya sesi
 * aktif, dan menyimpannya sebagai kolom akan membuat dua sumber kebenaran
 * yang bisa berbeda.
 */
class StationPresenter
{
    public static function one(Station $station, ?BillingSession $session): array
    {
        return [
            'id' => $station->id,
            'code' => $station->code,
            'name' => $station->name,
            // Teks bebas, bukan enum — tiap rental punya penamaan sendiri.
            'console_type' => $station->stationType?->name,
            // Status MASTER DATA, bukan status sesi. Station ACTIVE tetap bisa kosong.
            'status' => $station->status->value,
            'session' => self::session($session),
            'device' => self::device($station->device),
        ];
    }

    /**
     * Ringkasan sesi secukupnya untuk kartu dashboard — bukan objek `session`
     * penuh. Enam station x seluruh item dan payment akan membuat response
     * dashboard jauh lebih besar daripada yang dipakai menggambar kartunya.
     */
    private static function session(?BillingSession $session): ?array
    {
        if ($session === null) {
            return null;
        }

        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'mode' => $session->mode->value,
            // null saat PENDING_PAYMENT — timer belum jalan.
            'started_at' => $session->started_at?->toIso8601ZuluString(),
            /*
             * null juga untuk Postpaid (DEC-034) — itu artinya tidak ada batas
             * waktu, dan client menghitung MAJU dari `started_at`.
             */
            'end_at' => $session->end_at?->toIso8601ZuluString(),
            'customer_label' => $session->customerLabel(),
            'balance_due' => $session->totals()->balanceDue(),
        ];
    }

    private static function device(?Device $device): ?array
    {
        if ($device === null) {
            return null;
        }

        return [
            'id' => $device->id,
            // Dihitung dari last_seen_at, tidak disimpan — kolom status yang
            // disimpan pasti basi begitu heartbeat berhenti.
            'status' => $device->isOnline() ? 'ONLINE' : 'OFFLINE',
            'last_seen_at' => $device->last_seen_at?->toIso8601ZuluString(),
            'app_version' => $device->app_version,
        ];
    }
}
