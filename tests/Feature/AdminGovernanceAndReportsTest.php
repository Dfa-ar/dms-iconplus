<?php

namespace Tests\Feature;

use App\Models\KantorPerwakilan;
use App\Models\PaOrder;
use App\Models\QcRejectReason;
use App\Models\Region;
use App\Models\Role;
use App\Models\SlaSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGovernanceAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_qc_reasons_and_changes_are_audited(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.qc-reject-reasons.index'))
            ->assertOk()
            ->assertSee('Alasan Penolakan QC');

        $this->actingAs($admin)
            ->post(route('admin.qc-reject-reasons.store'), [
                'code' => 'foto_tidak_jelas',
                'label' => 'Foto tidak jelas',
                'description' => 'Bukti foto tidak bisa diverifikasi.',
            ])
            ->assertRedirect();

        $reason = QcRejectReason::where('code', 'foto_tidak_jelas')->firstOrFail();
        $this->assertTrue($reason->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.qc-reject-reasons.update', $reason), [
                'code' => 'foto_tidak_jelas',
                'label' => 'Foto kurang jelas',
                'description' => 'Perlu verifikasi ulang.',
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('qc_reject_reasons', [
            'id' => $reason->id,
            'label' => 'Foto kurang jelas',
            'is_active' => 0,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master_created',
            'entity' => 'QcRejectReason',
            'entity_id' => $reason->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master_updated',
            'entity' => 'QcRejectReason',
            'entity_id' => $reason->id,
        ]);
    }

    public function test_admin_can_update_sla_and_configurable_bast_number_format(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Pola nomor BAST');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'sla_days' => 21,
                'aging_green_max' => 3,
                'aging_yellow_max' => 8,
                'aging_orange_max' => 16,
                'bast_number_format' => '{kp_code}-{year}-{sequence}/BAST',
            ])
            ->assertRedirect();

        $settings = SlaSetting::current();
        $this->assertSame(21, $settings->sla_days);
        $this->assertSame('{kp_code}-{year}-{sequence}/BAST', $settings->bast_number_format);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'master_updated',
            'entity' => 'SlaSetting',
            'entity_id' => $settings->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'sla_days' => 21,
                'aging_green_max' => 10,
                'aging_yellow_max' => 8,
                'aging_orange_max' => 16,
                'bast_number_format' => '{sequence}/{kp_code}/{year}',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('aging_green_max');
    }

    public function test_admin_can_browse_audit_log_and_export_filtered_xlsx(): void
    {
        $admin = $this->createAdmin();
        $office = KantorPerwakilan::create(['nama' => 'KP Bandung', 'kode' => 'BDG']);
        $region = Region::create([
            'kabupaten_kota' => 'Kota Bandung',
            'kantor_perwakilan_id' => $office->id,
        ]);
        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-REPORT-001',
            'customer_id' => 'CUST-REPORT-001',
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'pa_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Audit Log');

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Laporan Excel');

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.xlsx', [
                'report_type' => 'pa',
                'kantor_perwakilan_id' => $office->id,
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report_exported',
            'entity' => 'Report',
        ]);

        foreach (['officers', 'kp', 'kendala', 'bast', 'payments'] as $reportType) {
            $reportResponse = $this->actingAs($admin)->get(route('admin.reports.xlsx', [
                'report_type' => $reportType,
                'kantor_perwakilan_id' => $office->id,
            ]));
            $reportResponse->assertOk();
            $this->assertStringStartsWith('PK', $reportResponse->streamedContent(), "{$reportType} export should be an XLSX workbook.");
        }
        $this->assertNotNull($paOrder->fresh());
    }

    public function test_saved_bast_number_pattern_is_used_when_generating_a_document(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $office = KantorPerwakilan::create(['nama' => 'KP Bandung', 'kode' => 'BDG']);
        $region = Region::create([
            'kabupaten_kota' => 'Kota Bandung',
            'kantor_perwakilan_id' => $office->id,
        ]);
        SlaSetting::current()->update(['bast_number_format' => '{kp_code}-{year}-{sequence}/BAST']);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
            'current_status' => PaOrder::QC_STATUS_PASSED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.bast.generate'), [
                'kantor_perwakilan_id' => $office->id,
                'pa_ids' => [$paOrder->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bast_documents', [
            'kantor_perwakilan_id' => $office->id,
            'nomor_bast' => 'BDG-' . now()->year . '-0001/BAST',
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
    }
}