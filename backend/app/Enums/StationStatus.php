<?php

namespace App\Enums;

/**
 * API.md §6 — status MASTER DATA station, bukan status sesi.
 * Station yang ACTIVE tetap bisa kosong atau terpakai.
 */
enum StationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case MAINTENANCE = 'MAINTENANCE';
    case DISABLED = 'DISABLED';

    /** Hanya ACTIVE yang boleh menerima session baru (409 STATION_NOT_AVAILABLE). */
    public function acceptsNewSession(): bool
    {
        return $this === self::ACTIVE;
    }
}
