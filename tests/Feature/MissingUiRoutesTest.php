<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissingUiRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
            'parent_group' => 'Bandung',
        ]);

        PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_admin_petugas_index_renders(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.petugas.index'));

        $response->assertOk();
    }

    public function test_super_admin_users_index_renders(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id, 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('system.users.index'));

        $response->assertOk();
    }

    public function test_petugas_dashboard_renders(): void
    {
        $role = Role::firstOrCreate(['name' => 'petugas']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('petugas.tasks.index'));

        $response->assertOk();
    }
}
