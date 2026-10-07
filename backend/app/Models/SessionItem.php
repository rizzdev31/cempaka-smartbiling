<?php

namespace App\Models;

use App\Enums\SessionItemType;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris Open Tab: RENTAL, FNB, EXTEND, DISCOUNT, ADJUSTMENT (API.md §7). */
#[Fillable([
    'session_id', 'type', 'name', 'qty', 'unit_price', 'subtotal',
    'is_paid', 'meta', 'created_by',
])]
class SessionItem extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return [
            'type' => SessionItemType::class,
            'is_paid' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BillingSession::class, 'session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
