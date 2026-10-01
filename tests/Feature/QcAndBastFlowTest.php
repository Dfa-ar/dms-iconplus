<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\Evidence;
use App\Models\FatPoint;
use App\Models\KantorPerwakilan;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\Splitter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class QcAndBastFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bast_page_explains_empty_kp_master_and_links_to_configuration(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bast.index'))
            ->assertOk()
            ->assertSee('Master Kantor Perwakilan masih kosong.')
            ->assertSee(route('admin.kantor-perwakilan.index'));
    }

    public function test_admin_can_review_qc_and_generate_bast(): void
    {
        Storage::fake('public');
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $kantor = KantorPerwakilan::create([
            'nama' => 'Kantor Perwakilan Tasikmalaya',
            'kode' => 'TSM',
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'kecamatan' => 'Cipedes',
            'kelurahan' => 'Indihiang',
            'parent_group' => 'Tasikmalaya',
            'kantor_perwakilan_id' => $kantor->id,
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $fatPoint = FatPoint::create([
            'kode_fat' => 'FAT-QC-001',
            'nama_fat' => 'FAT QC',
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $splitter = Splitter::create([
            'kode_splitter' => 'SPL-QC-001',
            'nama_splitter' => 'Splitter QC',
            'fat_point_id' => $fatPoint->id,
            'capacity_port' => 8,
            'total_port' => 8,
            'is_active' => true,
        ]);

        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-QC-001',
            'customer_id' => 'CUST-QC-001',
            'customer_name' => 'Pelanggan QC',
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'current_officer_id' => $officer->id,
            'id_pln' => 'PLN-QC-001',
            'id_pln_confirmed' => true,
            'kwh_status' => 'TIDAK_DITEMUKAN',
            'kwh_note' => 'Tidak ditemukan saat pemeriksaan.',
            'fat_point_id' => $fatPoint->id,
            'splitter_id' => $splitter->id,
            'port_number' => 1,
        ]);
        foreach (['k3_awal', 'ont_depan', 'ont_belakang_sn', 'fat_terdekat', 'k3_akhir', 'ba_pengambilan_perangkat'] as $type) {
            Evidence::create([
                'pa_id' => $paOrder->id,
                'type' => $type,
                'file_path' => "evidence/{$paOrder->pa_number}/{$type}.jpg",
                'uploaded_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.qc.index'))
            ->assertOk()
            ->assertSee('QC massal')
            ->assertSee('A. K3 awal')
            ->assertSee('F. BA Pengambilan');

        $this->actingAs($admin)
            ->post(route('admin.qc.review', $paOrder), [
                'decision' => 'approve',
                'qc_note' => 'Semua foto dan SN terbaca jelas.',
                'qc_checks' => array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'qc_status' => 'PASSED',
            'current_status' => PaOrder::QC_STATUS_PASSED,
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'to_status' => PaOrder::QC_STATUS_PASSED,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.bast.generate'), [
                'kantor_perwakilan_id' => $kantor->id,
                'pa_ids' => [$paOrder->id],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bast_documents', [
            'created_by' => $admin->id,
            'kantor_perwakilan_id' => $kantor->id,
            'nomor_bast' => '0001/BAST/TSM/' . now()->year,
        ]);
        $this->assertDatabaseHas('bast_items', [
            'pa_id' => $paOrder->id,
        ]);
        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'from_status' => PaOrder::QC_STATUS_PASSED,
            'to_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
        ]);

        $document = \App\Models\BastDocument::where('kantor_perwakilan_id', $kantor->id)->firstOrFail();
        $this->actingAs($admin)
            ->from(route('admin.bast.index'))
            ->post(route('admin.bast.generate'), [
                'kantor_perwakilan_id' => $kantor->id,
                'pa_ids' => [$paOrder->id],
            ])
            ->assertRedirect(route('admin.bast.index'))
            ->assertSessionHasErrors('pa_ids');
        $this->assertDatabaseCount('bast_documents', 1);

        $this->actingAs($admin)
            ->from(route('admin.bast.index'))
            ->post(route('admin.bast.void', $document), [])
            ->assertRedirect(route('admin.bast.index'))
            ->assertSessionHasErrors('void_reason');

        $this->actingAs($admin)
            ->post(route('admin.bast.void', $document), ['void_reason' => 'Nomor dokumen perlu dikoreksi.'])
            ->assertRedirect(route('admin.bast.index'));

        $this->assertDatabaseHas('bast_documents', [
            'id' => $document->id,
            'status' => 'VOID',
            'void_reason' => 'Nomor dokumen perlu dikoreksi.',
            'voided_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_VOID,
            'bast_document_id' => null,
        ]);
        $this->assertDatabaseMissing('bast_items', ['bast_document_id' => $document->id, 'pa_id' => $paOrder->id]);
        $this->assertDatabaseHas('bast_item_archives', [
            'bast_document_id' => $document->id,
            'pa_id' => $paOrder->id,
            'archive_reason' => 'BAST dibatalkan: Nomor dokumen perlu dikoreksi.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'bast_voided',
            'entity_id' => $document->id,
        ]);

        $this->actingAs($admin)->get(route('admin.bast.preview', $document))->assertOk();
        $voidPdfText = (new Parser())->parseContent(Storage::disk('public')->get($document->file_url))->getText();
        $this->assertStringContainsString('VOID', $voidPdfText);
    }

    public function test_bast_items_cannot_reference_the_same_pa_twice(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $paOrder = PaOrder::factory()->create(['region_id' => $region->id]);
        $document = \App\Models\BastDocument::create([
            'nomor_bast' => '0001/BAST/TEST/' . now()->year,
            'region_id' => $region->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'FINAL',
        ]);
        \App\Models\BastItem::create([
            'bast_document_id' => $document->id,
            'pa_id' => $paOrder->id,
        ]);

        $this->expectException(QueryException::class);
        \App\Models\BastItem::create([
            'bast_document_id' => $document->id,
            'pa_id' => $paOrder->id,
        ]);
    }

    public function test_batch_generation_rejects_a_pa_from_another_kp(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $selectedOffice = KantorPerwakilan::create(['nama' => 'KP Tasikmalaya', 'kode' => 'TSM']);
        $otherOffice = KantorPerwakilan::create(['nama' => 'KP Bandung', 'kode' => 'BDG']);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
            'kantor_perwakilan_id' => $otherOffice->id,
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'kantor_perwakilan_id' => $otherOffice->id,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.bast.index'))
            ->post(route('admin.bast.generate'), [
                'kantor_perwakilan_id' => $selectedOffice->id,
                'pa_ids' => [$paOrder->id],
            ])
            ->assertRedirect(route('admin.bast.index'))
            ->assertSessionHasErrors('pa_ids');

        $this->assertDatabaseCount('bast_documents', 0);
        $this->assertDatabaseMissing('bast_items', ['pa_id' => $paOrder->id]);
    }

    public function test_archived_duplicate_bast_items_remain_in_historical_document_content(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-ARCHIVED-001',
            'region_id' => $region->id,
            'customer_id' => 'CUST-ARCHIVED-001',
        ]);
        $document = \App\Models\BastDocument::create([
            'nomor_bast' => '0001/BAST/OLD/2026',
            'region_id' => $region->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'VOID',
        ]);
        \App\Models\BastItemArchive::create([
            'original_item_id' => 999001,
            'bast_document_id' => $document->id,
            'pa_id' => $paOrder->id,
            'serial_number' => 'SN-ARCHIVED',
            'location' => 'Bandung',
            'archive_reason' => 'Duplikat historis',
            'archived_at' => now(),
        ]);

        $this->assertStringContainsString('PA-ARCHIVED-001', $document->toPdfText());
        $this->assertStringContainsString('Alasan pembatalan: -', $document->toPdfText());
        $this->assertSame(1, $document->allItems()->count());
    }

    public function test_admin_can_bulk_review_pa_atomically(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $fatPoint = FatPoint::create([
            'kode_fat' => 'FAT-BULK-001',
            'nama_fat' => 'FAT Bulk',
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $splitter = Splitter::create([
            'kode_splitter' => 'SPL-BULK-001',
            'nama_splitter' => 'Splitter Bulk',
            'fat_point_id' => $fatPoint->id,
            'capacity_port' => 8,
            'total_port' => 8,
            'is_active' => true,
        ]);
        $evidenceTypes = ['k3_awal', 'ont_depan', 'ont_belakang_sn', 'fat_terdekat', 'k3_akhir', 'ba_pengambilan_perangkat'];
        $paOrders = collect();

        foreach (['PA-BULK-001', 'PA-BULK-002', 'PA-BULK-MISSING'] as $index => $paNumber) {
            $paOrder = PaOrder::factory()->create([
                'pa_number' => $paNumber,
                'region_id' => $region->id,
                'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
                'qc_status' => PaOrder::QC_STATUS_PENDING,
                'id_pln' => "PLN-BULK-00" . ($index + 1),
                'id_pln_confirmed' => true,
                'kwh_status' => 'TIDAK_DITEMUKAN',
                'kwh_note' => 'Tidak ditemukan.',
                'kabel_panjang_meter' => 10,
                'fat_point_id' => $fatPoint->id,
                'splitter_id' => $splitter->id,
                'port_number' => $index + 1,
            ]);
            $paOrders->push($paOrder);

            if ($paNumber !== 'PA-BULK-MISSING') {
                foreach ($evidenceTypes as $type) {
                    Evidence::create([
                        'pa_id' => $paOrder->id,
                        'type' => $type,
                        'file_path' => "evidence/{$paNumber}/{$type}.jpg",
                        'uploaded_by' => $admin->id,
                    ]);
                }
            }
        }

        $payload = [
            'decision' => 'approve',
            'qc_checks' => array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true),
        ];

        $this->actingAs($admin)
            ->from(route('admin.qc.index'))
            ->post(route('admin.qc.bulk-review'), $payload + ['pa_ids' => $paOrders->pluck('id')->all()])
            ->assertRedirect(route('admin.qc.index'))
            ->assertSessionHasErrors('pa_ids');

        $this->assertSame(0, PaOrder::query()->whereIn('id', $paOrders->pluck('id'))->where('qc_status', PaOrder::QC_STATUS_PASSED)->count());

        $this->actingAs($admin)
            ->post(route('admin.qc.bulk-review'), $payload + ['pa_ids' => $paOrders->take(2)->pluck('id')->all()])
            ->assertRedirect(route('admin.qc.index'))
            ->assertSessionHas('success', 'QC approve tersimpan untuk 2 PA.');

        foreach ($paOrders->take(2) as $paOrder) {
            $this->assertDatabaseHas('pa_orders', [
                'id' => $paOrder->id,
                'current_status' => PaOrder::QC_STATUS_PASSED,
                'qc_status' => PaOrder::QC_STATUS_PASSED,
            ]);
        }
    }

    public function test_admin_can_fill_formal_bast_party_details(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $kantor = KantorPerwakilan::create([
            'nama' => 'Kantor Perwakilan Bandung',
            'kode' => 'BDG',
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Bandung Wetan',
            'kelurahan' => 'Jawa Barat',
            'parent_group' => 'Bandung',
            'kantor_perwakilan_id' => $kantor->id,
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);

        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-BAST-FORMAL-002',
            'customer_name' => 'Pelanggan Formal',
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'current_officer_id' => $officer->id,
            'qc_status' => 'PASSED',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bast.index', ['kantor_perwakilan_id' => $kantor->id]))
            ->assertOk()
            ->assertSee('Pihak Yang Menyerahkan')
            ->assertSee('Pihak Yang Menerima')
            ->assertSee('Jabatan Penyerah');

        $this->actingAs($admin)
            ->post(route('admin.bast.generate'), [
                'kantor_perwakilan_id' => $kantor->id,
                'pa_ids' => [$paOrder->id],
                'pihak_menyerahkan' => 'CV Icon Plus Bandung',
                'pihak_menerima' => 'PT PLN UID Bandung',
                'jabatan_menyerahkan' => 'Manager Operasional',
                'jabatan_menerima' => 'PIC Pelanggan',
                'lokasi' => 'Bandung',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bast_documents', [
            'pihak_menyerahkan' => 'CV Icon Plus Bandung',
            'pihak_menerima' => 'PT PLN UID Bandung',
            'jabatan_menyerahkan' => 'Manager Operasional',
            'jabatan_menerima' => 'PIC Pelanggan',
            'lokasi' => 'Bandung',
        ]);
    }

    public function test_bast_template_pdf_support_is_available(): void
    {
        $this->assertFileExists(storage_path('app/templates/bast-template.pdf'));
        $this->assertTrue(class_exists(\setasign\Fpdi\Fpdi::class), 'FPDI library harus tersedia agar template PDF bisa dipakai untuk BAST.');
    }

    public function test_bast_document_regenerates_when_existing_pdf_is_stale(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Bandung Wetan',
            'kelurahan' => 'Jawa Barat',
            'parent_group' => 'Bandung',
        ]);

        $document = \App\Models\BastDocument::create([
            'nomor_bast' => '0001/BAST/BANDUNG/2026',
            'region_id' => $region->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'FINAL',
            'notes' => 'Legacy draft',
            'pihak_menyerahkan' => 'Legacy PT',
            'pihak_menerima' => 'Legacy Customer',
            'jabatan_menyerahkan' => 'Legacy Jabatan',
            'jabatan_menerima' => 'Legacy PIC',
            'lokasi' => 'Bandung',
        ]);

        $relativePath = 'bast/' . now()->format('Y') . '/' . \Str::slug($document->nomor_bast, '_') . '.pdf';
        Storage::disk('public')->makeDirectory(dirname($relativePath));

        $legacyPdf = new \setasign\Fpdi\Fpdi();
        $legacyPdf->AddPage();
        $legacyPdf->SetFont('Helvetica', '', 12);
        $legacyPdf->Text(10, 10, 'LEGACY PDF VERSION');
        Storage::disk('public')->put($relativePath, $legacyPdf->Output('S'));

        $document->update(['file_url' => $relativePath]);

        $controller = new \App\Http\Controllers\Admin\BastController();
        $method = new \ReflectionMethod($controller, 'ensurePdfExists');
        $method->setAccessible(true);
        $method->invoke($controller, $document);

        $content = Storage::disk('public')->get($relativePath);
        $pdfText = (new Parser())->parseContent($content)->getText();

        $this->assertStringContainsString('BERITA ACARA', $pdfText);
        $this->assertStringNotContainsString('LEGACY PDF VERSION', $pdfText);
    }

    public function test_admin_can_download_final_bast_pdf(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Cibiru',
            'kelurahan' => 'Cisurupan',
            'parent_group' => 'Bandung',
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);

        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-BAST-001',
            'customer_id' => 'CUST-BAST-TEST-001',
            'customer_name' => 'Pelanggan BAST',
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'current_officer_id' => $officer->id,
            'qc_status' => 'PASSED',
        ]);

        $document = \App\Models\BastDocument::create([
            'nomor_bast' => '0001/BAST/BANDUNG/2026',
            'region_id' => $region->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'FINAL',
            'notes' => 'BAST final',
        ]);

        \App\Models\BastItem::create([
            'bast_document_id' => $document->id,
            'pa_id' => $paOrder->id,
            'serial_number' => 'SN-123456',
            'location' => 'Bandung',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.bast.download', $document));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, public')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $document->refresh();
        $this->assertNotEmpty($document->file_url);
        $this->assertTrue(Storage::disk('public')->exists($document->file_url));

        $downloadParser = new Parser();
        $downloadPdf = $downloadParser->parseContent(Storage::disk('public')->get($document->file_url));
        $downloadPdfText = $downloadPdf->getText();
        $this->assertCount(2, $downloadPdf->getPages());
        $this->assertStringContainsString('BERITA ACARA', $downloadPdfText);
        $this->assertStringContainsString('CUST-BAST-TEST-001', $downloadPdfText);
        $this->assertStringContainsString('SN-123456', $downloadPdfText);

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment; filename=', $contentDisposition ?? '');

        $preview = $this->actingAs($admin)
            ->get(route('admin.bast.preview', $document));

        $preview->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $previewPdfText = (new Parser())->parseContent(Storage::disk('public')->get($document->file_url))->getText();
        $this->assertStringContainsString('BERITA ACARA', $previewPdfText);

        $previewDisposition = $preview->headers->get('Content-Disposition');
        $this->assertStringContainsString('inline; filename=', $previewDisposition ?? '');
    }
}
