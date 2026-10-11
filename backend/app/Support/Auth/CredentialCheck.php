<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pemeriksaan username + password, dipakai BERSAMA oleh API (tablet) dan
 * Admin Web.
 *
 * ## Kenapa dibagi, bukan ditulis dua kali
 *
 * Tiga aturan di bawah mudah terlihat seperti detail yang bisa
 * disederhanakan saat seseorang menulis login kedua. Kalau masing-masing
 * permukaan punya salinannya sendiri, salah satunya akan melenceng — dan
 * yang melenceng diam-diam adalah yang berhubungan dengan keamanan.
 *
 * Audit ikut di sini, bukan di controller, karena PRD §24 mewajibkan login
 * tercatat — dari permukaan mana pun.
 */
final class CredentialCheck
{
    /** Lihat [self::dummyHash]. */
    private static ?string $dummyHash = null;

    public function __construct(private readonly AuditLogger $audit) {}

    public function attempt(string $username, string $password): CredentialResult
    {
        $user = User::query()->where('username', $username)->first();

        /*
         * Hash::check dijalankan JUGA saat user tidak ada, memakai hash
         * dummy. Tanpa itu, username yang salah menjawab jauh lebih cepat
         * daripada password yang salah — dan selisih waktu itu cukup untuk
         * menebak username mana yang terdaftar.
         */
        $passwordValid = Hash::check($password, $user?->password ?? self::dummyHash());

        if ($user === null || ! $passwordValid) {
            $this->audit->forAnonymous(AuditAction::LOGIN_FAILED, [
                'actor_id' => $user?->id,
                'actor_name' => $user?->name,
                'actor_role' => $user?->role->value,
                'after' => ['username' => $username],
            ]);

            return new CredentialResult(CredentialOutcome::INVALID, $user);
        }

        /*
         * PRD §6: akun dinonaktifkan tidak boleh masuk, tapi barisnya tetap
         * ada supaya histori transaksinya tidak kehilangan pemilik.
         */
        if (! $user->is_active) {
            $this->audit->forUser($user, AuditAction::LOGIN_BLOCKED_INACTIVE);

            return new CredentialResult(CredentialOutcome::INACTIVE, $user);
        }

        return new CredentialResult(CredentialOutcome::OK, $user);
    }

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
}
