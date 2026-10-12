<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PATCH /customers/{customer}` — DEC-049.
 *
 * Aturannya sama persis dengan `StoreCustomerRequest`, dengan satu
 * perbedaan yang wajib: `unique` harus MENGABAIKAN baris ini sendiri.
 * Tanpa itu, menyimpan form tanpa mengubah nomor akan ditolak dengan
 * "nomor ini sudah terdaftar atas nama lain" — padahal nama lain itu
 * dirinya sendiri.
 */
class UpdateCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'sometimes', 'nullable', 'string', 'max:32',
                Rule::unique('customers', 'phone')->ignore($this->route('customer')?->id),
            ],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama customer tidak boleh dikosongkan.',
            'phone.unique' => 'Nomor telepon ini sudah terdaftar atas nama lain.',
        ];
    }
}
