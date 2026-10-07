<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use App\Support\Api\ApiResponse;
use App\Support\Api\ErrorCode;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Presenters\UserPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
    /** Hash pembanding untuk username yang tidak ada. Lihat login(). */
    private static ?string $dummyHash = null;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Hash acak yang tidak akan pernah cocok, dibuat dengan cost yang sama
     * seperti hash asli supaya waktu pemeriksaannya setara.
     *
     * Harus hash bcrypt yang sah — string sembarang membuat Hash::check
     * melempar "This password does not use the Bcrypt algorithm", dan login
     * dengan username tak terdaftar jadi 500 alih-alih 401.
     */
    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(32));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('username', $request->string('username'))->first();

        /*
         * Hash::check dijalankan juga saat user tidak ada, memakai hash dummy.
         * Tanpa itu, username yang salah menjawab jauh lebih cepat daripada
         * password yang salah — dan selisih waktu itu cukup untuk menebak
         * username mana yang terdaftar.
         */
        $passwordValid = Hash::check(
            (string) $request->string('password'),
            $user?->password ?? self::dummyHash(),
        );

        if ($user === null || ! $passwordValid) {
            $this->audit->forAnonymous(AuditAction::LOGIN_FAILED, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'actor_role' => $user?->role->value,
                'after' => ['username' => $request->string('username')->toString()],
            ]);

            // Pesan sama untuk username salah maupun password salah —
            // jangan memberi tahu username mana yang ada.
            throw new ApiException(
                ErrorCode::INVALID_CREDENTIALS,
                'Username atau password salah.',
                401,
            );
        }

        // PRD §6: akun dinonaktifkan tidak boleh masuk, tapi barisnya tetap ada
        // supaya histori transaksinya tidak kehilangan pemilik.
        if (! $user->is_active) {
            $this->audit->forUser($user, AuditAction::LOGIN_BLOCKED_INACTIVE);

            throw new ApiException(
                ErrorCode::USER_INACTIVE,
                'Akun ini dinonaktifkan. Hubungi admin.',
                403,
            );
        }

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
