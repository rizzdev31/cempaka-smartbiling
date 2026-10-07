<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fnb_order_id', 'fnb_product_id', 'name', 'qty', 'unit_price', 'subtotal'])]
class FnbOrderItem extends Model
{
    use HasUuidKey;

    public function order(): BelongsTo
    {
        return $this->belongsTo(FnbOrder::class, 'fnb_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(FnbProduct::class, 'fnb_product_id');
    }
}
