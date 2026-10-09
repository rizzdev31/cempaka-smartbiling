<?php

namespace App\Support\Presenters;

use App\Models\Package;

/** Objek `package` — API.md §6. */
class PackagePresenter
{
    public static function one(Package $package): array
    {
        $package->loadMissing('stationType');

        return [
            'id' => $package->id,
            'name' => $package->name,
            'duration_minutes' => (int) $package->duration_minutes,
            'price' => (int) $package->price,
            /*
             * Dihitung server (API.md §6). Client memakainya untuk menampilkan
             * estimasi harga extend — harga finalnya tetap dari server (DEC-007).
             */
            'hourly_rate' => $package->hourlyRate(),
            /*
             * DEC-019 — paket milik satu tipe konsol. Tanpa dua field ini,
             * Flutter tidak bisa tahu paket mana yang boleh dipakai di station
             * yang dipilih, dan server akan menolaknya saat start session.
             */
            'station_type_id' => $package->station_type_id,
            'console_type' => $package->stationType?->name,
            'is_active' => (bool) $package->is_active,
        ];
    }
}
