<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/** DEC-020 — hanya owner yang boleh mengubah tarif. */
class UserRoleTest extends TestCase
{
    public function test_hanya_owner_boleh_mengubah_tarif(): void
    {
        $this->assertTrue(UserRole::OWNER->canManagePricing());

        // Admin biasa TIDAK boleh — ini inti DEC-020, yang memisahkan
        // "Admin / Owner" dari PRD §6.
        $this->assertFalse(UserRole::ADMIN->canManagePricing());
        $this->assertFalse(UserRole::OPERATOR->canManagePricing());
    }

    public function test_owner_dan_admin_sama_sama_level_admin(): void
    {
        $this->assertTrue(UserRole::OWNER->isAdminLevel());
        $this->assertTrue(UserRole::ADMIN->isAdminLevel());
        $this->assertFalse(UserRole::OPERATOR->isAdminLevel());
    }

    public function test_hanya_ada_tiga_role(): void
    {
        $this->assertCount(3, UserRole::cases());
    }
}
