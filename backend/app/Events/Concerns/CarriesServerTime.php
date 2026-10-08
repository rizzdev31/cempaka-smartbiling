<?php

namespace App\Events\Concerns;

use Illuminate\Support\Carbon;

/**
 * Setiap payload broadcast membawa `server_time` — REALTIME.md §1 butir 4.
 *
 * Client memakainya untuk menyegarkan offset (DEC-003). Tanpa ini, device yang
 * jamnya meleset hanya akan terkoreksi saat ada request HTTP, padahal justru
 * saat realtime ramai itulah timer paling sering dibaca.
 */
trait CarriesServerTime
{
    protected function serverTime(): string
    {
        return Carbon::now()->toIso8601ZuluString();
    }
}
