<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipe konsol — DEC-019. Pembawa tarif: paket menempel ke sini. */
#[Fillable(['name', 'sort_order', 'is_active'])]
class StationType extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }
}
