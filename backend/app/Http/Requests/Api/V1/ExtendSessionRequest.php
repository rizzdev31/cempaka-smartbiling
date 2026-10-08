<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /sessions/{id}/extend` — API.md §7, DEC-007.
 *
 * Aturan "kelipatan 30, minimum 30" sengaja TIDAK ditegakkan di sini:
 * kontrak meminta error code `EXTEND_DURATION_INVALID`, bukan
 * `VALIDATION_FAILED`. Yang diperiksa di sini hanya bentuk datanya.
 */
class ExtendSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'duration_minutes' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration_minutes.required' => 'Durasi extend wajib diisi.',
            'duration_minutes.integer' => 'Durasi extend harus berupa angka menit.',
        ];
    }
}
