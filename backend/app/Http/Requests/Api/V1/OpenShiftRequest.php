<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /shifts/open` — API.md §10. */
class OpenShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Boleh nol: tidak semua rental menaruh modal awal di laci.
            'opening_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['opening_cash.required' => 'Modal kas awal wajib diisi (boleh 0).'];
    }
}
