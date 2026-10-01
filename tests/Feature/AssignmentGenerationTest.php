<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\KantorPerwakilan;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_assignments_for_unassigned_pa_in_same_region(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
            'parent_group' => 'Bandung',
        ]);

        $officerA = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
            'daily_target' => 20,
        ]);

        $officerB = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
            'daily_target' => 20,
        ]);

        PaOrder::factory()->count(3)->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_UNASSIGNED,
            'current_officer_id' => null,
            'assigned_date' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.assignments.generate'), [
            'region_id' => $region->id,
            'target_per_officer' => 2,
        ]);

        $response->assertSessionHas('status');
        $this->assertSame(3, PaOrder::query()->where('current_status', PaOrder::STATUS_ASSIGNED)->count());
        $this->assertSame(3, \App\Models\Assignment::query()->count());
    }

    public function test_admin_can_preview_assignment_before_generating(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $region = Region::create(['kabupaten_kota' => 'Cirebon']);
        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-PREVIEW-001',
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_UNASSIGNED,
            'current_officer_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index', [
                'region_id' => $region->id,
                'target_per_officer' => 3,
            ]))
            ->assertOk()
            ->assertSee($officer->name)
            ->assertSee($paOrder->pa_number)
            ->assertSee('Kapasitas target');
    }

    public function test_admin_can_generate_assignments_for_all_regions_in_a_kp(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $office = KantorPerwakilan::create(['nama' => 'KP Bandung Raya', 'kode' => 'BDG']);
        $bandung = Region::create([
            'kabupaten_kota' => 'Kota Bandung',
            'parent_group' => 'Bandung Raya',
            'kantor_perwakilan_id' => $office->id,
        ]);
        $cimahi = Region::create([
            'kabupaten_kota' => 'Kota Cimahi',
            'parent_group' => 'Bandung Raya',
            'kantor_perwakilan_id' => $office->id,
        ]);
        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $bandung->id,
            'kantor_perwakilan_id' => $office->id,
            'is_active' => true,
        ]);
        $paOrders = PaOrder::factory()->count(2)->sequence(
            ['region_id' => $bandung->id],
            ['region_id' => $cimahi->id],
        )->create([
            'kantor_perwakilan_id' => $office->id,
            'current_status' => PaOrder::STATUS_UNASSIGNED,
            'current_officer_id' => null,
            'assigned_date' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.assignments.index', ['kantor_perwakilan_id' => $office->id]))
            ->assertOk()
            ->assertSee($office->nama)
            ->assertSee($paOrders[0]->pa_number)
            ->assertSee($paOrders[1]->pa_number);

        $this->actingAs($admin)
            ->post(route('admin.assignments.generate'), [
                'kantor_perwakilan_id' => $office->id,
                'target_per_officer' => 2,
            ])
            ->assertSessionHas('status');

        $this->assertSame(2, PaOrder::query()->where('current_status', PaOrder::STATUS_ASSIGNED)->count());
        $this->assertSame(2, \App\Models\Assignment::query()->where('officer_id', $officer->id)->count());
    }

    public function test_assignment_generation_sends_whatsapp_reminder_to_assigned_officer(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
            'parent_group' => 'Bandung',
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
            'phone' => '6281234567890',
            'daily_target' => 20,
        ]);

        PaOrder::factory()->count(2)->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_UNASSIGNED,
            'current_officer_id' => null,
            'assigned_date' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assignments.generate'), [
                'region_id' => $region->id,
                'target_per_officer' => 2,
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $officer->user_id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);

        $this->assertTrue(Notification::query()->where('user_id', $officer->user_id)->exists());
    }

    public function test_admin_can_withdraw_assignment_before_work_starts(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $region = Region::create(['kabupaten_kota' => 'Tasikmalaya']);
        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'current_officer_id' => $officer->id,
            'assigned_date' => now()->toDateString(),
        ]);
        $assignment = \App\Models\Assignment::factory()->create([
            'pa_id' => $paOrder->id,
            'officer_id' => $officer->id,
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.assignments.reassign', $assignment), ['officer_id' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_UNASSIGNED,
            'current_officer_id' => null,
        ]);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'to_status' => PaOrder::STATUS_UNASSIGNED,
        ]);
    }
}
