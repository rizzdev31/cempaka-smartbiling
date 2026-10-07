<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `GET /api/v1/health` — API.md §5.
 *
 * Tanpa auth: dipakai tablet dan TV untuk tes jaringan
 * (TEST-PLAN-SABTU.md N2/N3) sebelum ada token apa pun.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::data([
            'status' => 'ok',
            'version' => config('app.api_version'),
            'database' => $this->databaseStatus(),
            // Reverb belum dipasang (langkah 12). Jangan melaporkan "ok"
            // untuk sesuatu yang belum ada.
            'broadcast' => 'not_configured',
        ]);
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }
}
