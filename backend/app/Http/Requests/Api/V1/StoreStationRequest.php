<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /stations` — DEC-048. Owner saja (ditegakkan Service). */
class StoreStationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /*
             * Kode bebas, tapi tidak boleh berisi titik.
             *
             * Ia dipakai sebagai nama channel realtime
             * (`private-station.{code}`), dan titik adalah pemisah segmen di
             * sana. "ST.01" akan menghasilkan channel yang tidak pernah bisa
             * dilanggani siapa pun — tanpa error, hanya diam.
             */
            'code' => ['required', 'string', 'max:16', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('stations', 'code')],
            'name' => ['required', 'string', 'max:255'],
            // Boleh kosong: station bisa dipasang sebelum konsolnya datang.
            'station_type_id' => ['nullable', 'uuid'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode station ini sudah dipakai.',
            'code.regex' => 'Kode hanya boleh huruf, angka, tanda hubung, dan garis bawah — tanpa titik atau spasi.',
        ];
    }
}
