<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\StationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PATCH /stations/{station}` — DEC-048.
 *
 * Semua field `sometimes`: ini PATCH. Field yang tidak dikirim tidak boleh
 * ikut berubah, dan `required` di sini akan memaksa setiap pemanggil
 * mengirim ulang seluruh objek — yang berarti nilai lama bisa tertimpa oleh
 * salinan yang sudah basi.
 *
 * Siapa boleh mengubah field yang mana ditentukan `MasterDataService`,
 * bukan di sini.
 */
class UpdateStationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:16', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('stations', 'code')->ignore($this->route('station')?->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'station_type_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'required', Rule::enum(StationStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
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
