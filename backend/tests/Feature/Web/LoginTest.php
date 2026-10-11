<?php

namespace Tests\Feature\Web;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Login Admin Web — DEC-043.
 *
 * Login adalah satu-satunya pintu. Yang diuji di sini sebagian besar adalah
 * **penolakan** dan apa yang terjadi SESUDAH masuk — dua hal yang tidak
 * terlihat saat mencoba sendiri dengan password yang benar.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'username' => 'owner',
            'name' => 'Budi Owner',
            'password' => 'rahasia123',
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('web-login:owner|127.0.0.1');
        parent::tearDown();
    }

    // ── Pintu masuk ───────────────────────────────────────────────────

    public function test_tamu_yang_membuka_admin_dilempar_ke_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_halaman_depan_mengarah_ke_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_owner_bisa_masuk(): void
    {
        $user = $this->user();

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_bisa_masuk(): void
    {
        $this->user(['username' => 'admin', 'role' => UserRole::ADMIN]);

        $this->post('/login', ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
    }

    public function test_dashboard_terbuka_setelah_login(): void
    {
        $this->actingAs($this->user())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ringkasan Hari Ini')
            // Nama user asli, bukan placeholder yang tertinggal.
            ->assertSee('Budi Owner');
    }

    // ── Penolakan ─────────────────────────────────────────────────────

    public function test_password_salah_ditolak(): void
    {
        $this->user();

        $this->post('/login', ['username' => 'owner', 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_username_tak_terdaftar_memberi_pesan_yang_sama(): void
    {
        /*
         * Inti pengamanannya. Pesan yang berbeda akan memberi tahu username
         * mana yang terdaftar — dan itu separuh pekerjaan penebak password.
         */
        $this->user();

        $pesan = 'Username atau password salah.';

        $this->post('/login', ['username' => 'owner', 'password' => 'salah'])
            ->assertInvalid(['username' => $pesan]);

        $this->post('/login', ['username' => 'hantu', 'password' => 'salah'])
            ->assertInvalid(['username' => $pesan]);
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->user(['is_active' => false]);

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LOGIN_BLOCKED_INACTIVE]);
    }

    public function test_operator_tidak_boleh_masuk_admin_web(): void
    {
        /*
         * Operator memakai tablet. Dashboard ini menampilkan pendapatan
         * seluruh usaha — angka yang bukan urusannya.
         */
        $this->user(['username' => 'operator1', 'role' => UserRole::OPERATOR]);

        $this->post('/login', ['username' => 'operator1', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LOGIN_BLOCKED_ROLE]);
    }

    public function test_operator_ditolak_tanpa_meninggalkan_sesi(): void
    {
        /*
         * Role diperiksa SEBELUM Auth::login. Kalau dibalik, cookie login
         * yang sah tetap terbentuk, dan halaman mana pun yang lupa dipasangi
         * middleware akan menerimanya.
         */
        $this->user(['username' => 'operator1', 'role' => UserRole::OPERATOR]);

        $this->post('/login', ['username' => 'operator1', 'password' => 'rahasia123']);

        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_username_dan_password_wajib_diisi(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['username', 'password']);
    }

    // ── Sesudah masuk ─────────────────────────────────────────────────

    public function test_role_diturunkan_di_tengah_sesi_langsung_kehilangan_akses(): void
    {
        /*
         * Session bertahan berhari-hari. Tanpa pemeriksaan ulang tiap
         * request, menurunkan seseorang jadi operator tidak berpengaruh apa
         * pun sampai dia kebetulan logout sendiri.
         */
        $user = $this->user();
        $this->actingAs($user)->get('/admin')->assertOk();

        $user->update(['role' => UserRole::OPERATOR]);

        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_akun_dinonaktifkan_di_tengah_sesi_langsung_kehilangan_akses(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/admin')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_id_sesi_berganti_setelah_login(): void
    {
        /*
         * Session fixation: tanpa regenerate, ID sebelum login tetap dipakai
         * sesudahnya, dan penyerang yang bisa menanam ID itu lebih dulu ikut
         * masuk bersama korban.
         */
        $this->user();

        $this->get('/login');
        $sebelum = session()->getId();

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123']);

        $this->assertNotSame($sebelum, session()->getId());
    }

    public function test_logout_mengakhiri_sesi(): void
    {
        $this->actingAs($this->user())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/login');
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LOGOUT]);
    }

    public function test_logout_menolak_get(): void
    {
        // Lewat GET, logout bisa dipicu prefetch browser atau <img src>.
        $this->actingAs($this->user())->get('/logout')->assertStatus(405);
    }

    public function test_yang_sudah_login_tidak_melihat_form_login_lagi(): void
    {
        $this->actingAs($this->user())->get('/login')->assertRedirect('/admin');
    }

    public function test_kembali_ke_halaman_yang_dituju_setelah_login(): void
    {
        // Admin yang membuka bookmark /admin harus mendarat di sana, bukan
        // di halaman depan.
        $this->user();

        $this->get('/admin');

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123'])
            ->assertRedirect('/admin');
    }

    // ── Pembatasan percobaan ──────────────────────────────────────────

    public function test_percobaan_keenam_ditolak_dengan_sisa_waktu(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'owner', 'password' => 'salah']);
        }

        // `assertInvalid` mencocokkan sebagian, jadi sisa detiknya yang
        // berubah-ubah tidak membuat test ini rapuh.
        $this->post('/login', ['username' => 'owner', 'password' => 'salah'])
            ->assertInvalid(['username' => 'Terlalu banyak percobaan']);
    }

    public function test_password_benar_ikut_terblokir_setelah_batas(): void
    {
        /*
         * Yang penting: blokirnya berlaku pada KREDENSIALNYA, bukan sekadar
         * menolak yang salah. Kalau password benar tetap lolos saat
         * terblokir, pembatasnya tidak memperlambat penebakan sama sekali.
         */
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'owner', 'password' => 'salah']);
        }

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_berhasil_membersihkan_hitungan(): void
    {
        $this->user();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['username' => 'owner', 'password' => 'salah']);
        }

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123'])
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->assertFalse(RateLimiter::tooManyAttempts('web-login:owner|127.0.0.1', 1));
    }

    // ── Jejak ─────────────────────────────────────────────────────────

    public function test_login_berhasil_tercatat_di_audit(): void
    {
        $this->user();

        $this->post('/login', ['username' => 'owner', 'password' => 'rahasia123']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LOGIN_SUCCESS,
            'actor_name' => 'Budi Owner',
        ]);
    }

    public function test_login_gagal_tercatat_di_audit(): void
    {
        $this->user();

        $this->post('/login', ['username' => 'owner', 'password' => 'salah']);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LOGIN_FAILED]);
    }
}
