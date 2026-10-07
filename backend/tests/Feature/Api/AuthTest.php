<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Autentikasi — API.md §4, PRD §6, DEC-020. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function operator(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'username' => 'operator1',
            'password' => Hash::make('rahasia-test'),
            'role' => UserRole::OPERATOR,
        ]);
    }

    /**
     * Membuang user yang sudah di-resolve guard.
     *
     * Di produksi setiap request punya container sendiri, jadi tidak ada
     * yang tersimpan. Dalam test, satu container dipakai beberapa request
     * dan guard menyimpan user dari request sebelumnya — tanpa ini, token
     * yang sudah dicabut tampak masih sah dan test lolos padahal salah.
     */
    private function freshRequestState(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function login(string $username = 'operator1', string $password = 'rahasia-test')
    {
        return $this->postJson('/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function test_login_benar_mengembalikan_token_user_dan_active_shift(): void
    {
        $this->operator(['name' => 'Budi']);

        $response = $this->login();

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'username', 'role', 'permissions'],
                    'active_shift',
                ],
                'meta' => ['server_time'],
            ])
            ->assertJsonPath('data.user.name', 'Budi')
            ->assertJsonPath('data.user.role', 'OPERATOR')
            // Belum buka shift -> null, bukan tidak ada field-nya.
            ->assertJsonPath('data.active_shift', null);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_active_shift_terisi_kalau_ada_shift_terbuka(): void
    {
        $user = $this->operator();

        $shift = Shift::query()->create([
            'operator_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 200000,
        ]);

        $this->login()
            ->assertOk()
            ->assertJsonPath('data.active_shift.id', $shift->id);
    }

    public function test_password_salah_ditolak_401(): void
    {
        $this->operator();

        $this->login(password: 'salah')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_username_tidak_ada_memberi_pesan_yang_sama_dengan_password_salah(): void
    {
        $this->operator();

        $tidakAda = $this->login(username: 'hantu');
        $passwordSalah = $this->login(password: 'salah');

        // Kalau pesannya berbeda, penyerang bisa memetakan username mana
        // yang terdaftar hanya dari respons login.
        $tidakAda->assertStatus(401);
        $this->assertSame(
            $passwordSalah->json('error.message'),
            $tidakAda->json('error.message'),
        );
    }

    public function test_akun_nonaktif_ditolak_403(): void
    {
        $this->operator(['is_active' => false]);

        $this->login()
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'USER_INACTIVE');
    }

    public function test_username_atau_password_kosong_ditolak_422(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['username', 'password']]]);
    }

    public function test_me_butuh_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_me_mengembalikan_bentuk_user_yang_sama_dengan_login(): void
    {
        $this->operator(['name' => 'Budi']);

        $token = $this->login()->json('data.token');

        $me = $this->withToken($token)->getJson('/api/v1/auth/me');

        $me->assertOk()
            ->assertJsonPath('data.user.username', 'operator1')
            ->assertJsonPath('data.user.role', 'OPERATOR');

        // Client membandingkan keduanya saat reconcile (API.md §12), jadi
        // bentuknya harus identik.
        $this->assertSame(
            $this->login()->json('data.user'),
            $me->json('data.user'),
        );
    }

    public function test_logout_mencabut_token_yang_dipakai(): void
    {
        $this->operator();
        $token = $this->login()->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->freshRequestState();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_logout_tidak_mematikan_token_perangkat_lain(): void
    {
        $this->operator();

        $tablet = $this->login()->json('data.token');
        $laptop = $this->login()->json('data.token');

        $this->withToken($tablet)->postJson('/api/v1/auth/logout')->assertOk();
        $this->freshRequestState();

        $this->withToken($laptop)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_akun_dinonaktifkan_setelah_login_langsung_kehilangan_akses(): void
    {
        $user = $this->operator();
        $token = $this->login()->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $user->update(['is_active' => false]);
        $this->freshRequestState();

        // Tanpa middleware active.user, token lama tetap jalan sampai
        // kedaluwarsa — operator yang baru diberhentikan masih bisa
        // menerima pembayaran.
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'USER_INACTIVE');
    }

    public function test_login_dibatasi_lima_kali_per_menit(): void
    {
        $this->operator();

        for ($i = 0; $i < 5; $i++) {
            $this->login(password: 'salah')->assertStatus(401);
        }

        $this->login(password: 'salah')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_ATTEMPTS');
    }

    public function test_login_dan_kegagalannya_tercatat_di_audit(): void
    {
        $this->operator();

        $this->login(password: 'salah');
        $this->login();

        $actions = DB::table('audit_logs')->pluck('action')->all();

        $this->assertContains(AuditAction::LOGIN_FAILED, $actions);
        $this->assertContains(AuditAction::LOGIN_SUCCESS, $actions);
    }

    public function test_audit_login_membekukan_nama_dan_role_aktor(): void
    {
        $user = $this->operator(['name' => 'Budi']);

        $this->login();
        $user->update(['name' => 'Budi Diganti']);

        $row = DB::table('audit_logs')->where('action', AuditAction::LOGIN_SUCCESS)->first();

        // Audit yang ikut berubah saat nama user diganti tidak berguna
        // sebagai bukti.
        $this->assertSame('Budi', $row->actor_name);
        $this->assertSame('OPERATOR', $row->actor_role);
    }

    public function test_percobaan_login_username_tak_terdaftar_tetap_tercatat(): void
    {
        $this->login(username: 'hantu');

        $row = DB::table('audit_logs')->where('action', AuditAction::LOGIN_FAILED)->first();

        $this->assertNotNull($row);
        $this->assertNull($row->actor_id);
        $this->assertStringContainsString('hantu', $row->after);
    }
}
