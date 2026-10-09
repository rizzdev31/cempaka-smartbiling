<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
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
class Device extends Model implements Authenticatable
{
    /*
     * Authenticatable supaya Device bisa jadi hasil guard `device` —
     * dengan begitu `auth:device` dan otorisasi channel `private-station.*`
     * memakai mesin yang sama dengan user, bukan jalur buatan sendiri.
     */
    use AuthenticatableTrait;
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

    /**
     * Membuat token baru dan menyimpan HASH-nya.
     *
     * Token aslinya dikembalikan sekali ini saja — tidak pernah bisa dibaca
     * ulang dari database. Kalau TV kehilangan tokennya, jalannya mendaftar
     * ulang, bukan menanyakan yang lama.
     *
     * @return string Token mentah, HANYA kali ini.
     */
    public function issueToken(): string
    {
        $plain = bin2hex(random_bytes(32));

        $this->token_hash = hash('sha256', $plain);
        $this->revoked_at = null;
        $this->save();

        return $plain;
    }

    /**
     * Mencari device dari token mentah di header `X-Device-Token`.
     *
     * Mencocokkan hash, bukan tokennya — kalau database terbaca, isinya tidak
     * bisa dipakai menyamar jadi TV mana pun.
     */
    public static function fromToken(?string $plain): ?self
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $device = self::query()
            ->whereNull('revoked_at')
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        return $device;
    }
}
