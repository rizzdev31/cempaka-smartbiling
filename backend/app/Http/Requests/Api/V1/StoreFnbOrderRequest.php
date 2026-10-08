<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /sessions/{id}/fnb/orders` — API.md §8.
 *
 * Harga TIDAK ada di daftar rules, dan itu disengaja: harga selalu dari server
 * (PRD §8). Kalau client boleh mengirim harga, diskon bisa dibuat dari tablet.
 */
class StoreFnbOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pesanan tidak boleh kosong.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.qty.min' => 'Jumlah minimal 1.',
        ];
    }
}
