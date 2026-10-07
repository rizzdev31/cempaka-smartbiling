<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'phone', 'note'])]
class Customer extends Model
{
    use HasUuidKey;

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(BillingSession::class);
    }
}
