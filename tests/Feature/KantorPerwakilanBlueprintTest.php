<?php

namespace Tests\Feature;

use App\Models\KantorPerwakilan;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KantorPerwakilanBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_kp_seeder_links_unassigned_west_java_data_idempotently(): void
    {
        $this->seed(\Database\Seeders\RegionSeeder::class);
        $officeRegion = Region::where('kabupaten_kota', 'Kota Bandung')->firstOrFail();
        $role = Role::firstOrCreate(['name' => 'petugas']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $officer = Officer::factory()->create([
            'user_id' => $user->id,
            'region_id' => $officeRegion->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $officeRegion->id,
            'current_officer_id' => $officer->id,
        ]);

        $this->seed(\Database\Seeders\KantorPerwakilanDemoSeeder::class);
        $this->seed(\Database\Seeders\KantorPerwakilanDemoSeeder::class);

        $this->assertSame(7, KantorPerwakilan::count());
        $office = KantorPerwakilan::where('kode', 'DUMMY-BDG')->firstOrFail();
        $this->assertSame($office->id, $officeRegion->fresh()->kantor_perwakilan_id);
        $this->assertSame($office->id, $officer->fresh()->kantor_perwakilan_id);
        $this->assertSame($office->id, $paOrder->fresh()->kantor_perwakilan_id);
    }

    public function test_kantor_perwakilan_is_a_formal_entity_with_region_and_officer_links(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $kantor = KantorPerwakilan::create([
            'nama' => 'Kantor Perwakilan Tasikmalaya',
            'alamat' => 'Jl. Cikurubuk No. 1',
            'latitude' => -7.3274,
            'longitude' => 108.2207,
            'pic_nama' => 'Yuniar',
            'pic_unit' => 'Inventory KP Tasikmalaya',
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'kecamatan' => 'Cipedes',
            'kelurahan' => 'Indihiang',
            'parent_group' => 'Tasikmalaya',
            'kantor_perwakilan_id' => $kantor->id,
        ]);

        $officer = Officer::factory()->create([
            'name' => 'Petugas Tasikmalaya',
            'kantor_perwakilan_id' => $kantor->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);

        $this->assertSame($kantor->id, $region->kantorPerwakilan->id);
        $this->assertSame($kantor->id, $officer->kantorPerwakilan->id);
        $this->assertDatabaseHas('kantor_perwakilan', ['nama' => 'Kantor Perwakilan Tasikmalaya']);

        $this->actingAs($admin)
            ->get(route('admin.kantor-perwakilan.index'))
            ->assertOk()
            ->assertSee('Kode KP')
            ->assertSee('TSM');
    }
}
