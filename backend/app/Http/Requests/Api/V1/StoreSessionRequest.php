<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SessionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /sessions` — API.md §7. */
class StoreSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'station_id' => ['required', 'uuid'],
            'package_id' => ['required', 'uuid'],
            'mode' => ['required', Rule::enum(SessionMode::class)],

            /*
             * DEC-008: satu session satu customer. Keduanya boleh kosong —
             * walk-in non-member di-default "Walk-in" oleh service.
             * `customer_id` tidak divalidasi `exists` di sini supaya pesan
             * gagalnya tetap satu bentuk dengan station/package.
             */
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'station_id.required' => 'Station wajib dipilih.',
            'package_id.required' => 'Paket wajib dipilih.',
            'mode.required' => 'Mode pembayaran wajib dipilih.',
            'customer_id.exists' => 'Customer tidak ditemukan.',
        ];
    }
}
