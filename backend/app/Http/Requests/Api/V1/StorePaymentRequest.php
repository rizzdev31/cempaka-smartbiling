<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /sessions/{id}/payments` — API.md §7, PRD §21. */
class StorePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],

            // Integer rupiah tanpa desimal (DEC-005). Minimum 1: pembayaran
            // nol rupiah hanya menambah baris yang tidak berarti di laporan.
            'amount' => ['required', 'integer', 'min:1'],

            // Wajib untuk QRIS — diperiksa di service, bukan di sini, supaya
            // error code-nya PAYMENT_REFERENCE_REQUIRED sesuai kontrak.
            'reference' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'amount.required' => 'Jumlah pembayaran wajib diisi.',
            'amount.integer' => 'Jumlah pembayaran harus berupa angka rupiah bulat.',
            'amount.min' => 'Jumlah pembayaran minimal 1 rupiah.',
        ];
    }
}
