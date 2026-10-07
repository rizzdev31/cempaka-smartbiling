<?php

namespace App\Models;

use App\Enums\FnbOrderStatus;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'session_id', 'status', 'source', 'note', 'total', 'created_by'])]
class FnbOrder extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['status' => FnbOrderStatus::class];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BillingSession::class, 'session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FnbOrderItem::class);
    }
}
