<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** TV Agent — API.md §9. Endpoint-nya dipakai Tahap 2. */
#[Fillable([
    'device_uid', 'station_id', 'token_hash', 'revoked_at',
    'model', 'os_version', 'app_version',
    'last_seen_at', 'uptime_seconds', 'registered_at',
])]
#[Hidden(['token_hash'])]
class Device extends Model
{
    use HasUuidKey;

    /**
     * Ambang offline (API.md §9). Dikirim ke client sebagai
     * `meta.offline_threshold_seconds` supaya aturannya tidak diduplikasi
     * di Flutter.
     */
    public const OFFLINE_THRESHOLD_SECONDS = 120;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * ONLINE/OFFLINE dihitung, tidak disimpan — kolom status yang disimpan
     * pasti basi begitu heartbeat berhenti.
     */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(Carbon::now()->subSeconds(self::OFFLINE_THRESHOLD_SECONDS));
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
