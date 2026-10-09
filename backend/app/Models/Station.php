<?php

namespace App\Models;

use App\Enums\StationStatus;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** ST01..ST06+ — PRD §10. */
#[Fillable(['code', 'name', 'station_type_id', 'status', 'enrollment_code', 'sort_order'])]
class Station extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['status' => StationStatus::class];
    }

    /**
     * Kode pendaftaran TV (API.md §9). Huruf ambigu dibuang: 0/O dan 1/I/L
     * mudah salah baca, dan kode ini dibacakan teknisi ke layar TV lewat
     * remote — salah satu karakter berarti mengulang dari awal.
     */
    public static function generateEnrollmentCode(): string
    {
        $alfabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $kode = '';

        for ($i = 0; $i < 6; $i++) {
            $kode .= $alfabet[random_int(0, strlen($alfabet) - 1)];
        }

        return $kode;
    }

    public function stationType(): BelongsTo
    {
        return $this->belongsTo(StationType::class);
    }

    public function device(): HasOne
    {
        return $this->hasOne(Device::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BillingSession::class);
    }

    /**
     * Session yang sedang memakai station ini, kalau ada.
     *
     * Station dianggap kosong (`AVAILABLE` di dashboard) kalau ini `null` —
     * `AVAILABLE` bukan status yang disimpan di mana pun.
     */
    public function currentSession(): ?BillingSession
    {
        return $this->sessions()
            ->whereIn('status', BillingSession::occupyingStatuses())
            ->latest('created_at')
            ->first();
    }
}
