<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::OPERATOR,
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => UserRole::OWNER]);
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::ADMIN]);
    }

    /** PRD §6: akun dinonaktifkan -> 403 USER_INACTIVE. */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
