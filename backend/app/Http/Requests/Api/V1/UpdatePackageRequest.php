<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PATCH /packages/{id}` — DEC-020. Owner saja.
 *
 * `station_type_id` sengaja TIDAK bisa diubah. Memindahkan paket ke tipe
 * konsol lain akan membuat sesi lama yang memakainya seolah-olah dijalankan
 * di konsol yang berbeda — dan laporan per tipe konsol jadi salah ke belakang.
 * Kalau memang perlu, buat paket baru dan nonaktifkan yang lama.
 */
class UpdatePackageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'string', 'max:64',
                Rule::unique('packages')
                    ->where(fn ($q) => $q->where('station_type_id', $this->route('package')->station_type_id))
                    ->ignore($this->route('package')->id),
            ],
            'duration_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'price' => ['sometimes', 'integer', 'min:0'],
            // Menonaktifkan paket menyembunyikannya dari layar Start Session
            // tanpa menghapusnya — sesi lama tetap punya acuan paketnya.
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Tipe konsol ini sudah punya paket dengan nama tersebut.',
            'price.integer' => 'Harga harus berupa angka rupiah bulat, tanpa titik atau koma.',
        ];
    }
}
