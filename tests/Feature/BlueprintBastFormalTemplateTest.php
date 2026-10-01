<?php

namespace Tests\Feature;

use App\Models\BastDocument;
use App\Models\PaOrder;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintBastFormalTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bast_template_fields_match_blueprint_requirements(): void
    {
        $fields = BastDocument::blueprintTemplateFields();

        $this->assertArrayHasKey('nomor_bast', $fields);
        $this->assertArrayHasKey('pihak_menyerahkan', $fields);
        $this->assertArrayHasKey('pihak_menerima', $fields);
        $this->assertArrayHasKey('jabatan_menyerahkan', $fields);
        $this->assertArrayHasKey('jabatan_menerima', $fields);
        $this->assertSame('Nomor BAST', $fields['nomor_bast']);
        $this->assertSame('Pihak Yang Menyerahkan', $fields['pihak_menyerahkan']);
    }

    public function test_bast_pdf_content_includes_formal_template_sections(): void
    {
        $region = Region::create([
            'kabupaten_kota' => 'Tasikmalaya',
            'kecamatan' => 'Cipedes',
            'kelurahan' => 'Indihiang',
            'parent_group' => 'Tasikmalaya',
        ]);

        $admin = \App\Models\User::factory()->create();

        $document = BastDocument::create([
            'nomor_bast' => '0001/BAST/KP-TSM/2026',
            'region_id' => $region->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'FINAL',
            'notes' => 'Dokumen final',
            'pihak_menyerahkan' => 'CV Icon Plus',
            'pihak_menerima' => 'Pelanggan Demo',
            'jabatan_menyerahkan' => 'Manager Operasional',
            'jabatan_menerima' => 'PIC Pelanggan',
            'lokasi' => 'Tasikmalaya',
        ]);

        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-BAST-FORMAL-001',
            'customer_name' => 'Pelanggan Demo',
            'region_id' => $region->id,
            'current_status' => PaOrder::STATUS_DONE,
            'qc_status' => PaOrder::QC_STATUS_PASSED,
        ]);

        $document->items()->create([
            'pa_id' => $paOrder->id,
            'serial_number' => 'SN-FAKE-001',
            'location' => 'Tasikmalaya',
        ]);

        $pdf = (new \ReflectionClass($document))->getMethod('buildPdfContent');

        $this->assertStringContainsString('Pihak Yang Menyerahkan', $document->toPdfText());
        $this->assertStringContainsString('Pihak Yang Menerima', $document->toPdfText());
    }
}
