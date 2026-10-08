<?php

namespace App\Models;

use App\Enums\CustomerCreditType;
use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris buku besar saldo member — DEC-026. */
#[Fillable(['customer_id', 'type', 'amount', 'session_id', 'note', 'created_by'])]
class CustomerCredit extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['type' => CustomerCreditType::class];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BillingSession::class, 'session_id');
    }
}
