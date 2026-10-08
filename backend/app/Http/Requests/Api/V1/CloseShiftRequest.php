<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /shifts/{id}/close` — API.md §10.
 *
 * Selisih antara `closing_cash` dan kas yang seharusnya TIDAK menghalangi
 * penutupan. Shift yang tidak bisa ditutup karena selisih akan membuat operator
 * mengarang angka supaya bisa pulang; yang dibutuhkan adalah selisihnya
 * tercatat, bukan dipaksa nol.
 */
class CloseShiftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['closing_cash.required' => 'Kas akhir wajib dihitung dan diisi.'];
    }
}
