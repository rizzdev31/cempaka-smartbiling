<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CashExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `POST /shifts/{shift}/expenses` — DEC-046. */
class StoreExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'string', 'max:32', Rule::in(CashExpense::kategori())],
            /*
             * Keterangan WAJIB, dan minimal tiga huruf.
             *
             * Pengeluaran tanpa keterangan adalah persis yang tidak bisa
             * dijelaskan saat laporan diperiksa. Batas tiga huruf menutup
             * jalan keluar termudah — mengetik "x" demi melewati form.
             */
            'note' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Nominal pengeluaran minimal 1 rupiah.',
            'note.required' => 'Tulis keterangannya — ini yang akan dibaca saat laporan diperiksa.',
            'note.min' => 'Keterangannya terlalu pendek untuk bisa dipahami nanti.',
        ];
    }
}
