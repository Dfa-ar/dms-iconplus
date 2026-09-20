<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_officer_also_creates_login_account(): void
    {
        $admin = $this->userWithRole('admin');
        $region = Region::create(['kabupaten_kota' => 'Bandung']);

        $this->actingAs($admin)
            ->post(route('admin.petugas.store'), [
                'name' => 'Petugas Baru',
                'employee_code' => 'NIP-001',
                'phone' => '081234567890',
                'region_id' => $region->id,
                'daily_target' => 20,
                'is_active' => 1,
                'user_email' => 'petugas.baru@idms.test',
                'user_password' => 'password123',
            ])
            ->assertRedirect(route('admin.petugas.index'));

        $user = User::where('email', 'petugas.baru@idms.test')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertSame('petugas', $user->role?->name);
        $this->assertDatabaseHas('officers', [
            'employee_code' => 'NIP-001',
            'user_id' => $user->id,
        ]);
    }

    public function test_creating_officer_requires_login_credentials(): void
    {
        $admin = $this->userWithRole('admin');
        $region = Region::create(['kabupaten_kota' => 'Bandung']);

        $this->actingAs($admin)
            ->post(route('admin.petugas.store'), [
                'name' => 'Tanpa Akun',
                'employee_code' => 'NIP-002',
                'region_id' => $region->id,
            ])
            ->assertSessionHasErrors(['user_email', 'user_password']);

        $this->assertDatabaseCount('officers', 0);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::create(['name' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
