<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\Evidence;
use App\Models\KendalaReason;
use App\Models\KantorPerwakilan;
use App\Models\PaOrder;
use App\Models\PaymentBatch;
use App\Models\PaymentItem;
use App\Models\QcRejectReason;
use App\Models\Region;
use App\Models\Role;
use App\Models\StatusLog;
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

    public function test_pa_master_region_filter_lists_unique_city_names_only(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);

        Region::create([
            'kabupaten_kota' => 'Kota Bandung',
            'kecamatan' => null,
            'kelurahan' => null,
            'level' => 'kabupaten_kota',
        ]);
        Region::create([
            'kabupaten_kota' => 'Kota Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => null,
            'level' => 'kecamatan',
        ]);
        Region::create([
            'kabupaten_kota' => 'Kota Tasikmalaya',
            'kecamatan' => null,
            'kelurahan' => null,
            'level' => 'kabupaten_kota',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pa.index'))
            ->assertOk()
            ->assertViewHas('regions', function ($regions) {
                $values = $regions->pluck('kabupaten_kota')->all();

                return count($values) === 2
                    && $values[0] === 'Kota Bandung'
                    && $values[1] === 'Kota Tasikmalaya';
            });
    }

    public function test_dashboard_counts_pending_qc_from_qc_status_and_legacy_work_status(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $region = Region::create(['kabupaten_kota' => 'Bandung']);

        PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
        ]);
        PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pendingQc', 2)
            ->assertViewHas('funnel.3.count', 2);
    }

    public function test_dashboard_completed_counts_include_paid_pa_in_regions_and_status_chart(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $region = Region::create(['kabupaten_kota' => 'Bandung']);

        PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
        ]);
        PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PAID,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('done', 2)
            ->assertViewHas('funnel.4.count', 1)
            ->assertViewHas('statusChart.values', [0, 0, 0, 2, 0, 0])
            ->assertViewHas('regions', fn ($regions) => $regions->firstWhere('wilayah', 'Bandung')['done'] === 2);
    }

    public function test_top_kendala_counts_only_status_logs_from_today(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $region = Region::create(['kabupaten_kota' => 'Bandung']);
        $reason = KendalaReason::create(['label' => 'Kendala jaringan', 'is_active' => true]);

        foreach ([now(), now()->subDay()] as $changedAt) {
            $paOrder = PaOrder::factory()->create([
                'region_id' => $region->id,
                'current_status' => PaOrder::STATUS_KENDALA,
                'kendala_reason_id' => $reason->id,
            ]);

            StatusLog::create([
                'pa_id' => $paOrder->id,
                'from_status' => PaOrder::STATUS_ON_PROGRESS,
                'to_status' => PaOrder::STATUS_KENDALA,
                'kendala_reason_id' => $reason->id,
                'changed_at' => $changedAt,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('topKendala', fn ($rows) => $rows->count() === 1 && (int) $rows->first()->total === 1);
    }

    public function test_control_tower_filters_qc_bast_payment_and_compliance_metrics_by_kp_and_date(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $office = KantorPerwakilan::create(['nama' => 'KP Bandung', 'kode' => 'BDG']);
        $otherOffice = KantorPerwakilan::create(['nama' => 'KP Cirebon', 'kode' => 'CRB']);
        $region = Region::create([
            'kabupaten_kota' => 'Kota Bandung',
            'kantor_perwakilan_id' => $office->id,
        ]);
        $otherRegion = Region::create([
            'kabupaten_kota' => 'Kota Cirebon',
            'kantor_perwakilan_id' => $otherOffice->id,
        ]);

        $pending = PaOrder::factory()->create([
            'pa_number' => 'PA-DASH-PENDING',
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'pa_date' => now()->toDateString(),
        ]);
        foreach (['k3_awal', 'k3_akhir'] as $type) {
            Evidence::create(['pa_id' => $pending->id, 'type' => $type, 'file_path' => "evidence/{$type}.jpg"]);
        }

        $snReason = QcRejectReason::create(['code' => 'sn_ont_tidak_terbaca', 'label' => 'SN buram']);
        PaOrder::factory()->create([
            'pa_number' => 'PA-DASH-SN',
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'current_status' => PaOrder::QC_STATUS_REJECTED,
            'qc_status' => PaOrder::QC_STATUS_REJECTED,
            'qc_reject_reason_id' => $snReason->id,
            'pa_date' => now()->toDateString(),
        ]);

        $bastDocument = \App\Models\BastDocument::create([
            'nomor_bast' => '0001/BAST/BDG/' . now()->year,
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'FINAL',
        ]);
        PaOrder::factory()->create([
            'pa_number' => 'PA-DASH-BAST',
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
            'bast_document_id' => $bastDocument->id,
            'pa_date' => now()->toDateString(),
        ]);

        $paid = PaOrder::factory()->create([
            'pa_number' => 'PA-DASH-PAID',
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PAID,
            'payment_status' => 'PAID',
            'bast_document_id' => $bastDocument->id,
            'pa_date' => now()->toDateString(),
        ]);
        $paymentBatch = PaymentBatch::create([
            'payment_date' => now()->toDateString(),
            'method' => 'BANK_TRANSFER',
            'proof_path' => 'payments/test.pdf',
            'created_by' => $admin->id,
        ]);
        PaymentItem::create(['payment_batch_id' => $paymentBatch->id, 'pa_id' => $paid->id, 'amount' => 125000]);

        PaOrder::factory()->create([
            'pa_number' => 'PA-DASH-OTHER-KP',
            'region_id' => $otherRegion->id,
            'kantor_perwakilan_id' => $otherOffice->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'pa_date' => now()->toDateString(),
        ]);
        PaOrder::factory()->create([
            'pa_number' => 'PA-DASH-OLD',
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $office->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'pa_date' => now()->subDays(30)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', [
                'kantor_perwakilan_id' => $office->id,
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('pendingQc', 1)
            ->assertViewHas('bastIssued', 2)
            ->assertViewHas('paymentTotal', 125000.0)
            ->assertViewHas('k3Compliance', ['count' => 1, 'percentage' => 25.0])
            ->assertViewHas('snAutoRejected', 1)
            ->assertViewHas('funnel.0.count', 0)
            ->assertViewHas('funnel.3.count', 1);
    }

    public function test_control_tower_reports_qc_compliance_and_repeated_apd_rejections_per_officer(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $bandung = KantorPerwakilan::create(['nama' => 'KP Bandung QC', 'kode' => 'BDG-QC']);
        $cirebon = KantorPerwakilan::create(['nama' => 'KP Cirebon QC', 'kode' => 'CRB-QC']);
        $bandungRegion = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kantor_perwakilan_id' => $bandung->id,
        ]);
        $cirebonRegion = Region::create([
            'kabupaten_kota' => 'Cirebon',
            'kantor_perwakilan_id' => $cirebon->id,
        ]);
        $bandungOfficer = Officer::factory()->create([
            'name' => 'Petugas QC Bandung',
            'region_id' => $bandungRegion->id,
            'kantor_perwakilan_id' => $bandung->id,
            'is_active' => true,
        ]);
        $cirebonOfficer = Officer::factory()->create([
            'name' => 'Petugas QC Cirebon',
            'region_id' => $cirebonRegion->id,
            'kantor_perwakilan_id' => $cirebon->id,
            'is_active' => true,
        ]);

        $makeReviewedPa = function (string $number, Officer $officer, array $checklist, string $qcStatus, $paDate = null) use ($bandung, $cirebon, $bandungRegion, $cirebonRegion) {
            $office = $officer->kantor_perwakilan_id === $bandung->id ? $bandung : $cirebon;
            $region = $office->is($bandung) ? $bandungRegion : $cirebonRegion;

            return PaOrder::factory()->create([
                'pa_number' => $number,
                'region_id' => $region->id,
                'kantor_perwakilan_id' => $office->id,
                'current_officer_id' => $officer->id,
                'current_status' => $qcStatus,
                'qc_status' => $qcStatus,
                'qc_checklist' => $checklist,
                'pa_date' => $paDate ?? now()->toDateString(),
            ]);
        };

        $passedChecks = array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true);
        $passedPa = $makeReviewedPa('PA-QC-OFFICER-PASS', $bandungOfficer, $passedChecks, PaOrder::QC_STATUS_PASSED);
        $failedApdChecks = $passedChecks;
        $failedApdChecks['k3_awal'] = false;
        $firstApdReject = $makeReviewedPa('PA-QC-OFFICER-APD-1', $bandungOfficer, $failedApdChecks, PaOrder::QC_STATUS_REJECTED);
        $secondApdReject = $makeReviewedPa('PA-QC-OFFICER-APD-2', $bandungOfficer, $failedApdChecks, PaOrder::QC_STATUS_REJECTED);
        $failedSnChecks = $passedChecks;
        $failedSnChecks['sn_ont'] = false;
        $snReject = $makeReviewedPa('PA-QC-OFFICER-SN', $bandungOfficer, $failedSnChecks, PaOrder::QC_STATUS_REJECTED);
        $makeReviewedPa('PA-QC-OFFICER-OLD', $bandungOfficer, $passedChecks, PaOrder::QC_STATUS_PASSED, now()->subDays(20)->toDateString());
        $makeReviewedPa('PA-QC-OTHER-KP', $cirebonOfficer, $failedApdChecks, PaOrder::QC_STATUS_REJECTED);

        foreach ([
            [$firstApdReject, 'Checklist QC gagal: APD lengkap sebelum pekerjaan.'],
            [$secondApdReject, 'Checklist QC gagal: APD lengkap sebelum pekerjaan.'],
            [$snReject, 'SN ONT tidak terbaca.'],
        ] as [$paOrder, $note]) {
            $paOrder->statusLogs()->create([
                'from_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
                'to_status' => PaOrder::QC_STATUS_REJECTED,
                'changed_by' => $admin->id,
                'note' => $note,
                'changed_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('dashboard', [
                'kantor_perwakilan_id' => $bandung->id,
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Kepatuhan QC per Petugas')
            ->assertSee('Petugas QC Bandung')
            ->assertSee('Berulang');

        $rows = $response->viewData('officerCompliance');
        $bandungRow = $rows->first(fn ($row) => $row['officer']->is($bandungOfficer));
        $cirebonRow = $rows->first(fn ($row) => $row['officer']->is($cirebonOfficer));

        $this->assertNotNull($bandungRow);
        $this->assertSame(4, $bandungRow['qc_reviewed']);
        $this->assertSame(2, $bandungRow['k3_passed']);
        $this->assertSame(50.0, $bandungRow['k3_percentage']);
        $this->assertSame(2, $bandungRow['apd_rejections']);
        $this->assertSame(1, $bandungRow['sn_auto_rejections']);
        $this->assertNotNull($cirebonRow);
        $this->assertSame(0, $cirebonRow['qc_reviewed']);
        $this->assertSame($bandungOfficer->id, $passedPa->current_officer_id);
    }

    public function test_admin_petugas_index_renders(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.petugas.index'));

        $response->assertOk();
    }

    public function test_admin_map_renders(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('admin.map.index'));

        $response->assertOk()
            ->assertSee('Sebaran petugas aktif')
            ->assertSee('Kepadatan PA');

        $html = $response->getContent();
        $this->assertSame(1, preg_match('/<a(?=[^>]*data-nav-key="map")(?=[^>]*aria-current="page")(?=[^>]*class="nav-active)/', $html));
        $this->assertSame(0, preg_match('/<a(?=[^>]*data-nav-key="dashboard")(?=[^>]*aria-current="page")(?=[^>]*class="nav-active)/', $html));
    }

    public function test_map_summary_returns_region_data(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);

        $response = $this->actingAs($admin)->getJson(route('admin.map.summary'));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Bandung Raya');
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
