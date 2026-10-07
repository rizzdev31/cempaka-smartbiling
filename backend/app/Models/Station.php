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
#[Fillable(['code', 'name', 'station_type_id', 'status', 'sort_order'])]
class Station extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['status' => StationStatus::class];
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
