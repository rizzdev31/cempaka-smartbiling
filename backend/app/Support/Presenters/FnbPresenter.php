<?php

namespace App\Support\Presenters;

use App\Models\FnbOrder;
use App\Models\FnbOrderItem;
use App\Models\FnbProduct;

/** Bentuk objek `fnb_product` dan `fnb_order` — API.md §8. */
class FnbPresenter
{
    public static function product(FnbProduct $product): array
    {
        return [
            'id' => $product->id,
            'category' => $product->category,
            'name' => $product->name,
            'price' => (int) $product->price,
            'is_available' => (bool) $product->is_available,
            // null = stok tidak dilacak (API.md §8). Jangan dipaksa jadi 0 —
            // artinya berbeda: 0 berarti habis.
            'stock' => $product->stock === null ? null : (int) $product->stock,
        ];
    }

    public static function order(FnbOrder $order): array
    {
        $order->loadMissing(['items', 'session.station']);

        return [
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status->value,
            'session_id' => $order->session_id,
            'station_code' => $order->session?->station?->code,
            'items' => $order->items->map(fn (FnbOrderItem $item) => [
                'product_id' => $item->fnb_product_id,
                'name' => $item->name,
                'qty' => (int) $item->qty,
                'unit_price' => (int) $item->unit_price,
                'subtotal' => (int) $item->subtotal,
            ])->all(),
            'total' => (int) $order->total,
            'source' => $order->source,
            'note' => $order->note,
            'created_at' => $order->created_at?->toIso8601ZuluString(),
        ];
    }
}
