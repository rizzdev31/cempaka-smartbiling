<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\Api\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan `Idempotency-Key` pada POST yang membuat/mengubah uang & state
 * (API.md §3). Dipasang per-route, bukan global.
 *
 * Aturan yang ditegakkan:
 *   1. header hilang            -> 400 IDEMPOTENCY_KEY_REQUIRED
 *   2. bukan UUID v4            -> 400 IDEMPOTENCY_KEY_REQUIRED
 *   3. key sama + body sama     -> response identik + X-Idempotent-Replay: true
 *   4. key sama + body beda     -> 409 IDEMPOTENCY_KEY_REUSED
 *
 * Hanya response sukses (2xx) yang disimpan. Response gagal tidak disimpan
 * supaya client bisa memperbaiki body lalu mengirim ulang; kalau 422 ikut
 * disimpan, aksi yang salah ketik akan terkunci selamanya pada key itu.
 */
class EnforceIdempotency
{
    /** Retensi minimal menurut API.md §3 aturan 2. */
    private const RETENTION_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            throw new ApiException(
                ErrorCode::IDEMPOTENCY_KEY_REQUIRED,
                'Header Idempotency-Key wajib dikirim berupa UUID v4.',
                400,
            );
        }

        $scope = $this->scope($request);
        $hash = $this->requestHash($request);

        // Lock mencegah dua retry yang datang hampir bersamaan dieksekusi dua kali.
        // Yang kedua menunggu yang pertama selesai, lalu membaca hasilnya.
        $lock = Cache::lock("idempotency:{$scope}:{$key}", 15);
        $lock->block(10);

        try {
            $stored = DB::table('idempotency_keys')
                ->where('scope', $scope)
                ->where('key', $key)
                ->first();

            if ($stored !== null) {
                if (! hash_equals($stored->request_hash, $hash)) {
                    throw ApiException::conflict(
                        ErrorCode::IDEMPOTENCY_KEY_REUSED,
                        'Idempotency-Key ini sudah dipakai untuk permintaan yang berbeda.',
                    );
                }

                return $this->replay($stored);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                $this->store($scope, $key, $hash, $response);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Key milik satu operator tidak boleh menabrak milik operator lain,
     * dan key operator tidak boleh menabrak key device TV.
     */
    private function scope(Request $request): string
    {
        if ($user = $request->user()) {
            return 'user:'.$user->getAuthIdentifier();
        }

        if ($deviceToken = $request->header('X-Device-Token')) {
            return 'device:'.substr(hash('sha256', $deviceToken), 0, 48);
        }

        return 'anon';
    }

    private function requestHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            $request->getContent(),
        ]));
    }

    private function replay(object $stored): Response
    {
        return response()
            ->json(json_decode($stored->response_body, true), $stored->response_status)
            ->header('X-Idempotent-Replay', 'true');
    }

    private function store(string $scope, string $key, string $hash, Response $response): void
    {
        $now = Carbon::now('UTC');

        DB::table('idempotency_keys')->insertOrIgnore([
            'scope' => $scope,
            'key' => $key,
            'request_hash' => $hash,
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'created_at' => $now,
            'expires_at' => $now->copy()->addHours(self::RETENTION_HOURS),
        ]);
    }
}
