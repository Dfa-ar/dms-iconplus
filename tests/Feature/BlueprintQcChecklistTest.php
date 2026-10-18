<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use App\Models\Evidence;
use App\Models\QcRejectReason;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintQcChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_qc_blueprint_checklist_is_available_and_persists_values(): void
    {
        $checklist = PaOrder::blueprintQcChecklist();

        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F'], array_keys(PaOrder::blueprintQcCategories()));
        $this->assertArrayHasKey('sn_ont', $checklist);
        $this->assertSame('SN ONT terbaca', $checklist['sn_ont']);
        $this->assertArrayHasKey('port_lock', $checklist);

        $normalized = PaOrder::normalizeQcChecklist([
            'sn_ont' => true,
            'port_lock' => '0',
            'kabel' => '1',
        ]);

        $this->assertTrue($normalized['sn_ont']);
        $this->assertFalse($normalized['port_lock']);
        $this->assertTrue($normalized['kabel']);
    }

    public function test_admin_can_submit_qc_checklist_and_reject_when_required_item_fails(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'kecamatan' => 'Cipedes',
            'kelurahan' => 'Indihiang',
            'parent_group' => 'Tasikmalaya',
        ]);

        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-QC-CHECK-001',
            'region_id' => $region->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => null,
        ]);
        $reason = QcRejectReason::create([
            'code' => 'port_lock_test',
            'label' => 'Port lock belum sesuai',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.qc.review', $paOrder), [
            'decision' => 'reject',
            'qc_note' => 'Port lock belum sesuai.',
            'reason_id' => $reason->id,
            'qc_checks' => array_merge(
                array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true),
                ['k3_awal' => false, 'sn_ont' => false, 'port_lock' => false],
            ),
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'qc_status' => PaOrder::QC_STATUS_REJECTED,
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'to_status' => PaOrder::QC_STATUS_REJECTED,
        ]);

        $qcLog = $paOrder->fresh()->statusLogs()->latest('id')->firstOrFail();
        $this->assertStringContainsString('SN ONT tidak terbaca', $qcLog->note);
        $this->assertStringContainsString('APD lengkap sebelum pekerjaan', $qcLog->note);

        $this->assertNotNull($paOrder->fresh()->qc_checklist);
    }

    public function test_admin_can_approve_qc_when_photo_readability_check_is_marked_failed(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
            'sn_ont_readable' => false,
        ]);
        $checks = array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true);
        $checks['sn_ont'] = false;

        $this->actingAs($admin)
            ->post(route('admin.qc.review', $paOrder), [
                'decision' => 'approve',
                'qc_checks' => $checks,
            ])
            ->assertRedirect(route('admin.qc.index'))
            ->assertSessionHas('success', 'PA telah lolos QC.');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
            'current_status' => PaOrder::QC_STATUS_PASSED,
        ]);
        $this->assertFalse($paOrder->fresh()->qc_checklist['sn_ont']);
    }

    public function test_admin_can_approve_qc_massal_when_photo_check_is_marked_failed(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
            'sn_ont_readable' => false,
        ]);

        foreach (['k3_awal', 'ont_depan', 'ont_belakang_sn', 'fat_terdekat', 'k3_akhir', 'ba_pengambilan_perangkat'] as $type) {
            Evidence::create([
                'pa_id' => $paOrder->id,
                'type' => $type,
                'file_path' => "evidence/{$paOrder->pa_number}/{$type}.jpg",
                'uploaded_by' => $admin->id,
            ]);
        }

        $checks = array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true);
        $checks['sn_ont'] = false;

        $this->actingAs($admin)
            ->post(route('admin.qc.bulk-review'), [
                'decision' => 'approve',
                'pa_ids' => [$paOrder->id],
                'qc_checks' => $checks,
            ])
            ->assertRedirect(route('admin.qc.index'))
            ->assertSessionHas('success', 'QC approve tersimpan untuk 1 PA.');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
            'current_status' => PaOrder::QC_STATUS_PASSED,
        ]);
    }

    public function test_qc_index_renders_bulk_review_controls(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);

        PaOrder::factory()->count(2)->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'qc_status' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qc.index'))
            ->assertOk()
            ->assertSee('QC massal')
            ->assertSee(route('admin.qc.bulk-review'))
            ->assertSee('pa_ids[]');
    }

    public function test_qc_approval_is_blocked_when_checklist_categories_are_missing(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.qc.index'))
            ->post(route('admin.qc.review', $paOrder), ['decision' => 'approve'])
            ->assertRedirect(route('admin.qc.index'))
            ->assertSessionHasErrors('qc_checks');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_DONE,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
        ]);
    }

    public function test_qc_rejection_requires_a_reason(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
        ]);
        $checks = array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true);
        $checks['port_lock'] = false;

        $this->actingAs($admin)
            ->from(route('admin.qc.index'))
            ->post(route('admin.qc.review', $paOrder), [
                'decision' => 'reject',
                'qc_checks' => $checks,
            ])
            ->assertRedirect(route('admin.qc.index'))
            ->assertSessionHasErrors('reason_id');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
        ]);
    }
}
