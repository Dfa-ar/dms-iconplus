<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_pa_master_and_view_detail(): void
    {
        $admin = $this->userWithRole('admin');
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-FILTER-001',
            'customer_name' => 'Pelanggan Filter',
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_ASSIGNED,
        ]);
        PaOrder::factory()->create([
            'pa_number' => 'PA-OTHER-001',
            'region_id' => $region->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pa.index', ['search' => 'PA-FILTER-001']))
            ->assertOk()
            ->assertSee('PA-FILTER-001')
            ->assertDontSee('PA-OTHER-001');

        $this->actingAs($admin)
            ->get(route('admin.pa.show', $paOrder))
            ->assertOk()
            ->assertSee('Pelanggan Filter')
            ->assertSee('ASSIGNED');
    }

    public function test_admin_can_open_upload_page_but_petugas_cannot(): void
    {
        $admin = $this->userWithRole('admin');
        $petugas = $this->userWithRole('petugas');

        $this->actingAs($admin)
            ->get(route('admin.pa.upload'))
            ->assertOk()
            ->assertSee('Impor Excel / CSV');

        $this->actingAs($petugas)
            ->get(route('admin.pa.upload'))
            ->assertForbidden();
    }

    public function test_admin_can_export_filtered_pa_data(): void
    {
        $admin = $this->userWithRole('admin');
        $region = Region::create(['kabupaten_kota' => 'Bandung']);
        PaOrder::factory()->create([
            'pa_number' => 'PA-EXPORT-MATCH',
            'customer_name' => 'Pelanggan Export',
            'region_id' => $region->id,
        ]);
        PaOrder::factory()->create([
            'pa_number' => 'PA-EXPORT-OTHER',
            'region_id' => $region->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.export', ['search' => 'PA-EXPORT-MATCH']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('PA-EXPORT-MATCH', $content);
        $this->assertStringNotContainsString('PA-EXPORT-OTHER', $content);
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
