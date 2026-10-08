<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /sessions/{id}/swap` — API.md §7, PRD §15. */
class SwapSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target_station_id' => ['required', 'uuid'],
            // Alasan swap masuk audit log — PRD §24. Tidak wajib, tapi kalau
            // diisi harus tersimpan apa adanya.
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['target_station_id.required' => 'Station tujuan wajib dipilih.'];
    }
}
