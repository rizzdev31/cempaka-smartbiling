<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /customers` — API.md §6, DEC-027. */
class StoreCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            /*
             * Nomor telepon unik dan opsional. Unik karena itu satu-satunya
             * cara operator mencari member yang lupa namanya; opsional karena
             * memaksanya akan membuat operator mengarang nomor demi melewati
             * form, dan nomor karangan lebih buruk daripada kosong.
             *
             * Wajib kalau struk mau dikirim ke WA (DEC-031) — tapi itu urusan
             * layar checkout, bukan syarat mendaftar.
             */
            'phone' => ['nullable', 'string', 'max:32', 'unique:customers,phone'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama customer wajib diisi.',
            'phone.unique' => 'Nomor telepon ini sudah terdaftar atas nama lain.',
        ];
    }
}
