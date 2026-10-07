<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paket harga milik satu tipe konsol — DEC-019. */
#[Fillable(['station_type_id', 'name', 'duration_minutes', 'price', 'is_active', 'sort_order'])]
class Package extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stationType(): BelongsTo
    {
        return $this->belongsTo(StationType::class);
    }

    /**
     * `hourly_rate` = price / (duration_minutes / 60) — dihitung server
     * (API.md §6). Dasar rumus harga extend DEC-007.
     *
     * Dibulatkan ke atas supaya tarif per jam tidak pernah lebih murah dari
     * harga paket aslinya karena pembagian.
     */
    public function hourlyRate(): int
    {
        return (int) ceil($this->price / ($this->duration_minutes / 60));
    }
}
