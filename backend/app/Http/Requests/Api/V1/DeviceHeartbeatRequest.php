<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /devices/heartbeat` — API.md §9.
 *
 * `known_session_id` dan `known_end_at` adalah apa yang TV *kira* sedang
 * berjalan. Server membandingkannya dan menjawab `state_match` — itulah
 * mekanisme reconcile murah tanpa WebSocket, yang justru paling dibutuhkan
 * ketika WebSocket-nya sedang putus.
 */
class DeviceHeartbeatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'app_version' => ['nullable', 'string', 'max:32'],
            'uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'known_session_id' => ['nullable', 'uuid'],
            'known_end_at' => ['nullable', 'date'],
        ];
    }
}
