<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Support\Api\ApiResponse;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\CredentialCheck;
use App\Support\Auth\CredentialOutcome;
use App\Support\Presenters\UserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autentikasi — API.md §4.
 *
 * Token Sanctum. Permission TIDAK ditempel sebagai token ability: role
 * disimpan di user, jadi mengubah role langsung berlaku tanpa login ulang.
 * Kalau permission dibekukan di token, operator yang dinaikkan jadi owner
 * harus logout dulu — dan yang diturunkan tetap punya akses lama.
 */
class AuthController
{
    public function __construct(
        private readonly AuditLogger $audit,
        /*
         * Pemeriksaan kredensialnya DIBAGI dengan login Admin Web
         * (DEC-043). Aturan timing-safe, pesan yang tidak membocorkan
         * username, dan pemblokiran akun nonaktif tidak boleh punya dua
         * salinan yang bisa melenceng.
         */
        private readonly CredentialCheck $credentials,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->credentials->attempt(
            $request->string('username')->toString(),
            (string) $request->string('password'),
        );

        if ($result->outcome === CredentialOutcome::INVALID) {
            // Pesan sama untuk username salah maupun password salah —
            // jangan memberi tahu username mana yang ada.
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                'Username atau password salah.',
                401,
            );
        }

        if ($result->outcome === CredentialOutcome::INACTIVE) {
            throw new ApiException(
                ErrorCode::USER_INACTIVE,
                'Akun ini dinonaktifkan. Hubungi admin.',
                403,
            );
        }

        $user = $result->user;

        // Satu token per login. Token lama tidak dicabut: operator bisa
        // memakai tablet dan laptop sekaligus saat pengujian.
        $token = $user->createToken('operator-app')->plainTextToken;

        $this->audit->forUser($user, AuditAction::LOGIN_SUCCESS);

        return ApiResponse::data([
            'token' => $token,
            'user' => UserPresenter::one($user),
            'active_shift' => UserPresenter::activeShift($user->activeShift()),
        ]);
    }

    /**
     * Dipakai saat app dibuka ulang untuk cek token masih valid, dan sebagai
     * langkah pertama reconcile setelah reconnect (API.md §12).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::data([
            'user' => UserPresenter::one($user),
            'active_shift' => UserPresenter::activeShift($user->activeShift()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Hanya token yang dipakai sekarang yang dicabut — logout di tablet
        // tidak boleh mematikan sesi di perangkat lain.
        $request->user()->currentAccessToken()->delete();

        $this->audit->forUser($user, AuditAction::LOGOUT);

        return ApiResponse::data(['logged_out' => true]);
    }
}
