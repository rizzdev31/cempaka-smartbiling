<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH /fnb/products/{id}`.
 *
 * Izinnya dibedakan per field di service: `price` butuh owner (DEC-020),
 * sisanya cukup `fnb.manage`. "Mie Goreng habis" adalah kejadian harian yang
 * harus bisa ditangani operator sendiri.
 */
class UpdateFnbProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'integer', 'min:0'],
            // null = stok tidak dilacak (API.md §8). Berbeda dari 0, yang
            // berarti habis.
            'stock' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['price.integer' => 'Harga harus berupa angka rupiah bulat.'];
    }
}
