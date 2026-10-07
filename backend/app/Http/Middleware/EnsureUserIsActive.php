<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Support\Api\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak user yang dinonaktifkan setelah token-nya terbit (PRD §6).
 *
 * Tanpa ini, menonaktifkan akun tidak berpengaruh apa pun sampai token-nya
 * kedaluwarsa — operator yang baru diberhentikan masih bisa membuat session
 * dan menerima pembayaran dari tablet yang masih login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            // Token dicabut sekalian, supaya tablet tidak terus mencoba.
            $user->currentAccessToken()?->delete();

            throw new ApiException(
                ErrorCode::USER_INACTIVE,
                'Akun ini dinonaktifkan. Hubungi admin.',
                403,
            );
        }

        return $next($request);
    }
}
