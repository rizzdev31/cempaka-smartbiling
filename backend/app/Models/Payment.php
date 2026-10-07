<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembayaran manual — PRD §21. Cash & QRIS statis, dikonfirmasi operator. */
#[Fillable([
    'session_id', 'method', 'amount', 'status', 'reference', 'note',
    'actor_id', 'shift_id', 'confirmed_at',
])]
class Payment extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BillingSession::class, 'session_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
