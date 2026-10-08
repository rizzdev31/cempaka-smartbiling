<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FnbOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /fnb/orders/{id}/status` — API.md §8.
 *
 * Kesahihan transisinya diperiksa di service, bukan di sini: kontrak meminta
 * error code `FNB_STATUS_TRANSITION_INVALID`, bukan `VALIDATION_FAILED`.
 */
class UpdateFnbStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(FnbOrderStatus::class)]];
    }

    public function messages(): array
    {
        return ['status.required' => 'Status tujuan wajib diisi.'];
    }
}
