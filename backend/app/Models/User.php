<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Admin / Operator / Owner. Login memakai `username` (API.md §4), bukan email.
 */
#[Fillable(['name', 'username', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasUuidKey;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'operator_id');
    }

    /** Shift yang masih terbuka, kalau ada (API.md §4 `active_shift`). */
    public function activeShift(): ?Shift
    {
        return $this->shifts()->whereNull('closed_at')->latest('opened_at')->first();
    }

    /** DEC-020 — hanya owner yang boleh mengubah tarif. */
    public function canManagePricing(): bool
    {
        return $this->role->canManagePricing();
    }
}
