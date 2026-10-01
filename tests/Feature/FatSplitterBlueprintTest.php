<?php

namespace Tests\Feature;

use App\Models\FatPoint;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FatSplitterBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_fat_points_and_splitters(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Soreang',
            'kelurahan' => 'Soreang',
            'parent_group' => 'Bandung',
            'office_name' => 'Kantor Perwakilan Bandung',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fat-points.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.fat-points.store'), [
                'kode_fat' => 'FAT-001',
                'nama_fat' => 'FAT Soreang',
                'region_id' => $region->id,
                'is_active' => true,
            ])
            ->assertRedirect();

        $fatPoint = FatPoint::query()->first();
        $this->assertNotNull($fatPoint);

        $this->actingAs($admin)
            ->get(route('admin.splitters.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.splitters.store'), [
                'kode_splitter' => 'SP-001',
                'nama_splitter' => 'Splitter Soreang',
                'fat_point_id' => $fatPoint->id,
                'total_port' => 24,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('splitters', ['kode_splitter' => 'SP-001']);
    }
}
