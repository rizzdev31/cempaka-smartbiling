<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /sessions/{id}/checkout` — API.md §7.
 *
 * `use_credit` ditambahkan oleh DEC-026 (saldo member). Default `false`:
 * memakai saldo harus keputusan sadar operator di depan customer, bukan
 * perilaku diam-diam yang baru ketahuan saat customer melihat saldonya habis.
 */
class CheckoutSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Boleh array kosong: tagihan bisa nol kalau semuanya sudah dibayar
            // di muka (Prepaid tanpa F&B) atau tertutup saldo member.
            'payments' => ['present', 'array'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.reference' => ['nullable', 'string', 'max:64'],

            'use_credit' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.present' => 'Daftar pembayaran wajib dikirim (boleh kosong).',
            'payments.*.method.required' => 'Metode pembayaran wajib dipilih.',
            'payments.*.amount.required' => 'Jumlah pembayaran wajib diisi.',
        ];
    }
}
