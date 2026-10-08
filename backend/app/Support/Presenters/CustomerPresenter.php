<?php

namespace App\Support\Presenters;

use App\Models\Customer;

/** Objek `customer` — API.md §6. */
class CustomerPresenter
{
    public static function one(Customer $customer): array
    {
        $customer->loadMissing('membership');

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'membership' => $customer->membership === null ? null : [
                'tier' => $customer->membership->tier,
                'is_active' => (bool) $customer->membership->is_active,
                'joined_at' => $customer->membership->joined_at?->toIso8601ZuluString(),
            ],
            /*
             * Saldo ikut dikirim (DEC-026). Operator perlu melihatnya SEBELUM
             * checkout untuk memutuskan mencentang "pakai saldo" — kalau hanya
             * muncul setelah checkout, keputusannya sudah lewat.
             */
            'credit_balance' => $customer->creditBalance(),
        ];
    }
}
