<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `PATCH /station-types/{type}` — DEC-048. Owner saja (ditegakkan Service). */
class UpdateStationTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('station_types', 'name')->ignore($this->route('type')?->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
            /*
             * Tidak ada hapus, hanya nonaktif.
             *
             * Tipe konsol dipegang paket, station, dan setiap sesi yang
             * pernah berjalan di atasnya. Menghapusnya berarti membuang
             * dasar harga dari riwayat yang sudah dibayar — dan riwayat itu
             * adalah catatan uangnya.
             */
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Tipe konsol dengan nama ini sudah ada.'];
    }
}
