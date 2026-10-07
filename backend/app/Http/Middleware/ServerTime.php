<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sumber resmi server-time offset (DEC-003, API.md §1).
 *
 * Header `X-Server-Time` wajib ada di SEMUA response — termasuk error,
 * 401, dan 500 — karena client memakai offset ini untuk menghitung sisa
 * waktu. Client yang kehilangan offset akan salah menampilkan timer.
 *
 * `meta.server_time` juga disisipkan ke body JSON supaya client yang
 * tidak membaca header tetap mendapat nilainya.
 */
class ServerTime
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Middleware ini global supaya 404 route tak terdaftar ikut membawa
        // header. Tapi Admin Web (Tahap 3B) tidak perlu disentuh.
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return $response;
        }

        // Satu nilai dipakai untuk header dan body supaya tidak ada selisih.
        $now = Carbon::now('UTC')->toIso8601ZuluString();

        $response->headers->set('X-Server-Time', $now);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);

            if (is_array($payload)) {
                $payload['meta'] = ['server_time' => $now] + ($payload['meta'] ?? []);
                $response->setData($payload);
            }
        }

        return $response;
    }
}
