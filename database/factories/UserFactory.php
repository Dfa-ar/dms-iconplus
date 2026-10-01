<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // Shortcut untuk bikin user dengan role tertentu, mis. User::factory()->admin()->create()
    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => Role::firstOrCreate(['name' => 'admin'])->id]);
    }

    public function petugas(): static
    {
        return $this->state(fn () => ['role_id' => Role::firstOrCreate(['name' => 'petugas'])->id]);
    }

}
