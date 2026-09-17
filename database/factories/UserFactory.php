<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'Secret@123';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => Str::lower(fake()->unique()->userName()),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function superAdmin(): static
    {
        return $this->role(User::ROLE_SUPERADMIN);
    }

    public function admin(): static
    {
        return $this->role(User::ROLE_ADMIN);
    }

    public function editor(): static
    {
        return $this->role(User::ROLE_EDITOR);
    }

    public function viewer(): static
    {
        return $this->role(User::ROLE_VIEWER);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
