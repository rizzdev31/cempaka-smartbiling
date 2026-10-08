<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /customers/{id}/membership` — DEC-027, DEC-029.
 *
 * `session_id` opsional. Kalau diisi, biaya pendaftaran masuk Open Tab sesi itu
 * dan sesinya sekalian ditautkan ke customer — itu alur normalnya, karena
 * pendaftaran terjadi saat customer berdiri di meja kasir mau checkout.
 */
class StoreMembershipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'uuid'],
            // Teks bebas: tiap rental punya penamaan tier sendiri
            // (alasan yang sama dengan station_types.name).
            'tier' => ['nullable', 'string', 'max:32'],
        ];
    }
}
