<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /sessions/{id}/cancel` — API.md §7. */
class CancelSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Masuk audit log (PRD §24). Tidak wajib, tapi kalau diisi harus
            // tersimpan apa adanya.
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
