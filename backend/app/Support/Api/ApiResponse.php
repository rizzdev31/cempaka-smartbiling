<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

/**
 * Pembentuk response sesuai `docs/contracts/API.md` §2.
 *
 * Semua response API wajib lewat sini supaya bentuknya seragam:
 * sukses `{data, meta}`, gagal `{error, meta}`. `meta.server_time`
 * diisi middleware ServerTime, bukan di sini.
 */
class ApiResponse
{
    public static function data(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(
            array_filter(['data' => $data, 'meta' => $meta], fn ($v) => $v !== []),
            $status,
        );
    }

    /**
     * List dengan pagination (API.md §2).
     */
    public static function collection(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => $meta], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }
}
