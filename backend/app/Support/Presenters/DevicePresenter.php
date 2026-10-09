<?php

namespace App\Support\Presenters;

use App\Models\Device;

/** Objek `device` — API.md §9 `GET /devices`. */
class DevicePresenter
{
    public static function one(Device $device): array
    {
        $device->loadMissing('station');

        return [
            'id' => $device->id,
            'device_uid' => $device->device_uid,
            // null kalau belum dipetakan atau pemetaannya dicabut (PRD §10).
            'station' => $device->station === null ? null : [
                'id' => $device->station->id,
                'code' => $device->station->code,
                'name' => $device->station->name,
            ],
            /*
             * Dihitung server dari `last_seen_at`, tidak disimpan — kolom
             * status yang disimpan pasti basi begitu heartbeat berhenti.
             */
            'status' => $device->isOnline() ? 'ONLINE' : 'OFFLINE',
            'last_seen_at' => $device->last_seen_at?->toIso8601ZuluString(),
            'app_version' => $device->app_version,
            'model' => $device->model,
            'os_version' => $device->os_version,
            'registered_at' => $device->registered_at?->toIso8601ZuluString(),
        ];
    }
}
