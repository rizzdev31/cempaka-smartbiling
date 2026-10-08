<?php

namespace App\Support\Presenters;

use App\Models\BillingSession;
use App\Models\Payment;
use App\Models\SessionItem;

/**
 * Bentuk objek `session`, `session_item`, dan `payment` — API.md §7.
 *
 * Satu bentuk dipakai di SEMUA response dan event. Kalau tiap endpoint
 * menyusun JSON-nya sendiri, Flutter dan Kotlin harus menebak varian mana
 * yang mereka terima saat reconnect (API.md §12).
 *
 * `remaining_seconds` sengaja TIDAK ada: client menghitungnya dari `end_at`
 * ditambah server-time offset (PRD §16, DEC-003). Mengirim sisa detik membuat
 * timer ikut salah setiap kali response telat di jaringan.
 */
class SessionPresenter
{
    public static function one(BillingSession $session): array
    {
        $session->loadMissing(['station', 'customer', 'items.createdBy', 'payments']);

        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status->value,
            'mode' => $session->mode->value,

            'station' => $session->station === null ? null : [
                'id' => $session->station->id,
                'code' => $session->station->code,
                'name' => $session->station->name,
            ],

            'customer' => $session->customer === null ? null : [
                'id' => $session->customer->id,
                'name' => $session->customer->name,
            ],
            'customer_name' => $session->customer_name,

            'package' => [
                'id' => $session->package_id,
                'name' => $session->package_name,
                'duration_minutes' => (int) $session->package_duration_minutes,
                'price' => (int) $session->package_price,
            ],
            'hourly_rate' => (int) $session->hourly_rate,

            'started_at' => $session->started_at?->toIso8601ZuluString(),
            'end_at' => $session->end_at?->toIso8601ZuluString(),
            'ended_at' => $session->ended_at?->toIso8601ZuluString(),

            // Server yang memutuskan, client tidak menghitung sendiri (DEC-007).
            'extendable' => $session->isExtendable(),
            'extend_deadline_at' => $session->extendDeadlineAt()?->toIso8601ZuluString(),

            'items' => $session->items->map(fn (SessionItem $item) => self::item($item))->all(),
            'totals' => $session->totals()->toArray(),

            'created_at' => $session->created_at?->toIso8601ZuluString(),
            'updated_at' => $session->updated_at?->toIso8601ZuluString(),
        ];
    }

    public static function item(SessionItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type->value,
            'name' => $item->name,
            'qty' => (int) $item->qty,
            'unit_price' => (int) $item->unit_price,
            'subtotal' => (int) $item->subtotal,
            'is_paid' => (bool) $item->is_paid,
            'meta' => $item->meta,
            'created_by' => UserPresenter::actor($item->createdBy),
            'created_at' => $item->created_at?->toIso8601ZuluString(),
        ];
    }

    public static function payment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'method' => $payment->method->value,
            'amount' => (int) $payment->amount,
            'status' => $payment->status,
            'reference' => $payment->reference,
            'confirmed_at' => $payment->confirmed_at?->toIso8601ZuluString(),
            'actor' => UserPresenter::actor($payment->actor),
        ];
    }
}
