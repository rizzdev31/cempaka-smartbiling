<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tiga user contoh — satu per role (DEC-020).
 *
 * Password diambil dari `SEED_PASSWORD` di `.env`. Nilai bawaannya hanya
 * untuk development; `.env.example` menandainya. Seeder TIDAK boleh
 * dijalankan di produksi — akun produksi dibuat lewat Admin Web (Tahap 3B).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'password');

        $users = [
            // Hanya role ini yang boleh mengubah tarif (DEC-020).
            ['name' => 'Owner', 'username' => 'owner', 'role' => UserRole::OWNER],
            ['name' => 'Admin', 'username' => 'admin', 'role' => UserRole::ADMIN],
            ['name' => 'Operator Kasir', 'username' => 'operator1', 'role' => UserRole::OPERATOR],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['username' => $user['username']],
                $user + ['password' => $password, 'is_active' => true],
            );
        }
    }
}
