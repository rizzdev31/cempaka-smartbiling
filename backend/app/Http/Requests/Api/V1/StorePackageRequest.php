<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /packages` — DEC-019, DEC-020. Owner saja. */
class StorePackageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // DEC-019: paket milik satu tipe konsol.
            'station_type_id' => ['required', 'uuid'],
            // Satu tipe konsol tidak boleh punya dua paket bernama sama.
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('packages')->where(
                    fn ($q) => $q->where('station_type_id', $this->input('station_type_id')),
                ),
            ],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            // Integer rupiah tanpa desimal (DEC-005).
            'price' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tipe konsol ini sudah punya paket dengan nama tersebut.',
            'price.integer' => 'Harga harus berupa angka rupiah bulat, tanpa titik atau koma.',
            'duration_minutes.max' => 'Durasi paket tidak masuk akal (lebih dari 24 jam).',
        ];
    }
}
