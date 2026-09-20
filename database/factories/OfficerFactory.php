<?php

namespace Database\Factories;

use App\Models\Officer;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Officer>
 */
class OfficerFactory extends Factory
{
    protected $model = Officer::class;

    public function definition(): array
    {
        return [
            'user_id' => null, // di-set eksplisit di seeder kalau petugas ini punya akun login
            'employee_code' => 'TK-'.$this->faker->unique()->numerify('######-').$this->faker->randomLetter(),
            'name' => $this->faker->name(),
            'phone' => '08'.$this->faker->numerify('##########'),
            'region_id' => Region::inRandomOrder()->value('id'),
            'is_active' => true,
            'daily_target' => 20,
        ];
    }

    // Petugas yang punya akun login sendiri (role petugas)
    public function withAccount(): static
    {
        return $this->afterCreating(function (Officer $officer) {
            $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

            $user = User::factory()->create([
                'name' => $officer->name,
                'role_id' => $petugasRole->id,
            ]);

            $officer->update(['user_id' => $user->id]);
        });
    }
}
