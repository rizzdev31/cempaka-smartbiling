<?php

namespace Tests\Feature\Api;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** RBAC tiga role — PRD §6, DEC-020. */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_setiap_permission_punya_gate(): void
    {
        // Menambah permission di enum tanpa Gate akan membuat
        // `middleware('can:...')` menolak semua orang tanpa penjelasan.
        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                Gate::has($permission->value),
                "Gate {$permission->value} belum terdaftar.",
            );
        }
    }

    public function test_hanya_owner_yang_lolos_gate_pricing_manage(): void
    {
        Route::get('/api/v1/_test/pricing', fn () => response()->json(['data' => ['ok' => true]]))
            ->middleware(['auth:sanctum', 'active.user', 'can:pricing.manage']);

        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();

        $this->actingAs($owner)->getJson('/api/v1/_test/pricing')->assertOk();

        // Inti DEC-020: admin biasa pun ditolak.
        $this->actingAs($admin)->getJson('/api/v1/_test/pricing')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($operator)->getJson('/api/v1/_test/pricing')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_operator_boleh_menjalankan_pekerjaan_kasir(): void
    {
        $operator = UserRole::OPERATOR;

        // PRD §6: station, session, payment, F&B, extend, swap, checkout, shift.
        foreach ([
            Permission::SESSION_CREATE,
            Permission::SESSION_EXTEND,
            Permission::SESSION_SWAP,
            Permission::SESSION_CHECKOUT,
            Permission::PAYMENT_CONFIRM,
            Permission::FNB_MANAGE,
            Permission::SHIFT_MANAGE,
            Permission::CUSTOMER_READ,
        ] as $permission) {
            $this->assertTrue(
                $operator->hasPermission($permission),
                "Operator seharusnya punya {$permission->value}.",
            );
        }
    }

    public function test_operator_boleh_mendaftarkan_member_baru(): void
    {
        // DEC-027 menjawab OD-014: boleh. Sebelumnya tidak, dan itu membuat
        // DEC-024 jadi jalan buntu — operator diminta menawarkan membership
        // saat checkout tapi tidak punya tombolnya.
        $this->assertTrue(UserRole::OPERATOR->hasPermission(Permission::CUSTOMER_CREATE));
        $this->assertTrue(UserRole::ADMIN->hasPermission(Permission::CUSTOMER_CREATE));
    }

    public function test_diskon_hanya_milik_owner(): void
    {
        // DEC-028 — diskon adalah pengurangan harga, jadi perlakuannya sama
        // dengan mengubah tarif (DEC-020).
        $this->assertTrue(UserRole::OWNER->hasPermission(Permission::DISCOUNT_MANAGE));
        $this->assertFalse(UserRole::ADMIN->hasPermission(Permission::DISCOUNT_MANAGE));
        $this->assertFalse(UserRole::OPERATOR->hasPermission(Permission::DISCOUNT_MANAGE));
    }

    public function test_owner_punya_semua_permission_admin(): void
    {
        $owner = UserRole::OWNER->permissions();

        foreach (UserRole::ADMIN->permissions() as $permission) {
            $this->assertContains($permission, $owner);
        }

        // Bedanya tepat dua: harga (DEC-020) dan diskon (DEC-028).
        $this->assertCount(count(UserRole::ADMIN->permissions()) + 2, $owner);
    }

    public function test_permissions_dikirim_ke_client_saat_login(): void
    {
        $owner = User::factory()->owner()->create([
            'username' => 'owner',
            'password' => bcrypt('rahasia-test'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'owner',
            'password' => 'rahasia-test',
        ]);

        $permissions = $response->json('data.user.permissions');

        $this->assertContains('pricing.manage', $permissions);
        $this->assertSame($owner->role->permissionValues(), $permissions);
    }

    public function test_user_nonaktif_tidak_lolos_gate_apa_pun(): void
    {
        $owner = User::factory()->owner()->inactive()->create();

        $this->assertFalse(Gate::forUser($owner)->allows(Permission::PRICING_MANAGE->value));
    }
}
