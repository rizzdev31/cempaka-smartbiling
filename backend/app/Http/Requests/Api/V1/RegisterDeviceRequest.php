<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** `POST /devices/register` — API.md §9. */
class RegisterDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Kode dari Admin. Kesahihannya diperiksa di service supaya error
            // code-nya ENROLLMENT_CODE_INVALID, bukan VALIDATION_FAILED.
            'enrollment_code' => ['required', 'string', 'max:8'],
            // android_id TV. Satu baris per perangkat fisik.
            'device_uid' => ['required', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'enrollment_code.required' => 'Kode pendaftaran wajib diisi.',
            'device_uid.required' => 'ID perangkat wajib dikirim.',
        ];
    }
}
