<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_account_management(): void
    {
        $admin = $this->userWithRole('admin');
        $petugas = $this->userWithRole('petugas');

        $this->actingAs($admin)->get(route('admin.accounts.index'))->assertOk();
        $this->actingAs($petugas)->get(route('admin.accounts.index'))->assertForbidden();
    }

    public function test_admin_can_create_a_login_account_for_an_unlinked_officer(): void
    {
        $admin = $this->userWithRole('admin');
        $officer = Officer::factory()->create(['user_id' => null]);

        $this->actingAs($admin)
            ->post(route('admin.accounts.store', $officer), [
                'email' => 'new.officer@idms.test',
                'password' => 'securepass123',
                'password_confirmation' => 'securepass123',
            ])
            ->assertRedirect(route('admin.accounts.index'));

        $user = $officer->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('petugas', $user->role?->name);
        $this->assertTrue(Hash::check('securepass123', $user->password));
    }

    public function test_admin_can_update_email_password_and_account_status(): void
    {
        $admin = $this->userWithRole('admin');
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);
        $user = User::factory()->create(['role_id' => $petugasRole->id]);
        $officer = Officer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->patch(route('admin.accounts.update', $officer), [
                'email' => 'updated.officer@idms.test',
                'is_active' => 0,
                'password' => 'changedpass123',
                'password_confirmation' => 'changedpass123',
            ])
            ->assertRedirect(route('admin.accounts.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated.officer@idms.test',
            'is_active' => 0,
        ]);
        $this->assertTrue(Hash::check('changedpass123', $user->fresh()->password));
        $this->assertFalse($officer->fresh()->is_active);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
