<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaUpload;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\UploadBatch;
use App\Models\User;
use App\Services\PaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_valid_csv_and_create_pa_orders(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $file = $this->csvFile([
            ['PA-001', 'CUST-001', 'Pelanggan Satu', '081234567890', 'Jl. Mawar 1', 'Bandung', 'Coblong', 'Dago', '2026-09-20'],
            ['PA-002', 'CUST-002', 'Pelanggan Dua', '081234567891', 'Jl. Melati 2', 'Bandung', 'Sukasari', 'Gegerkalong', '2026-09-19'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.pa.upload'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', '2 dari 2 baris berhasil diimpor. 0 diperbarui, 0 gagal.');
        $this->assertDatabaseCount('pa_orders', 2);
        $this->assertDatabaseHas('pa_orders', [
            'pa_number' => 'PA-001',
            'id_pln' => 'PLN-TEST',
            'current_status' => PaOrder::STATUS_UNASSIGNED,
        ]);
        $this->assertDatabaseCount('regions', 2);

        $batch = UploadBatch::firstOrFail();
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame(0, $batch->failed_rows);
        $this->assertNull($batch->error_report_path);
    }

    public function test_upload_rejects_unsupported_file_type(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $file = UploadedFile::fake()->create('pa.txt', 1, 'text/plain');

        $response = $this->actingAs($admin)->post(route('admin.pa.upload'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('upload_batches', 0);
        $this->assertDatabaseCount('pa_orders', 0);
    }

    public function test_upload_reports_rows_with_required_fields_missing(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $file = $this->csvFile([
            ['', 'CUST-INVALID', '', '081234567890', 'Jl. Kosong', '', 'Coblong', 'Dago', ''],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.pa.upload'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', '0 dari 1 baris berhasil diimpor. 0 diperbarui, 1 gagal.');
        $batch = UploadBatch::firstOrFail();
        $this->assertSame(1, $batch->total_rows);
        $this->assertSame(0, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        Storage::disk('local')->assertExists($batch->error_report_path);
        $this->assertStringContainsString('ID pelanggan/ID PLN', Storage::disk('local')->get($batch->error_report_path));
        $this->assertDatabaseCount('pa_orders', 0);
    }

    public function test_upload_rejects_missing_customer_and_pln_columns(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $file = UploadedFile::fake()->createWithContent('missing-identifiers.csv', implode("\n", [
            'pa_number,customer_name,kabupaten_kota,pa_date',
            'PA-NO-IDS,Pelanggan Tanpa ID,Bandung,2026-09-20',
        ]));

        $this->actingAs($admin)
            ->post(route('admin.pa.upload'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $batch = UploadBatch::firstOrFail();
        $this->assertSame('failed', $batch->status);
        $this->assertStringContainsString('customer_id, id_pln', Storage::disk('local')->get($batch->error_report_path));
        $this->assertDatabaseCount('pa_orders', 0);
    }

    public function test_non_admin_cannot_upload_pa_file(): void
    {
        Storage::fake('local');
        $petugas = $this->userWithRole('petugas');

        $this->actingAs($petugas)
            ->post(route('admin.pa.upload'), [
                'file' => $this->csvFile([
                    ['PA-001', 'CUST-001', 'Pelanggan Satu', '081234567890', 'Jl. Mawar 1', 'Bandung', 'Coblong', 'Dago', '2026-09-20'],
                ]),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('upload_batches', 0);
        $this->assertDatabaseCount('pa_orders', 0);
    }

    public function test_upload_preview_maps_headers_by_name(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->createWithContent('pa-reordered.csv', implode("\n", [
            'customer_name,pa_date,pa_number,kabupaten_kota',
            'Pelanggan Reordered,2026-09-20,PA-REORDERED,Bandung',
        ]));

        $this->actingAs($admin)
            ->post(route('admin.pa.upload.preview'), ['file' => $file])
            ->assertOk()
            ->assertSee('Pelanggan Reordered')
            ->assertSee('PA-REORDERED')
            ->assertSee('Hasil Mapping Header');
    }

    public function test_admin_can_download_upload_template(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.pa.upload.template'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('pa_number,customer_id,id_pln,customer_name', $response->streamedContent());
    }

    public function test_large_upload_is_sent_to_queue(): void
    {
        Storage::fake('local');
        Bus::fake();
        $admin = $this->admin();
        $lines = ['pa_number,customer_id,id_pln,customer_name,contact_phone,address,kabupaten_kota,kecamatan,kelurahan,pa_date'];
        for ($index = 1; $index <= 1001; $index++) {
            $lines[] = "PA-LARGE-{$index},CUST-{$index},PLN-{$index},Pelanggan {$index},081234567890,Jalan {$index},Bandung,Coblong,Dago,2026-09-20";
        }

        $response = $this->actingAs($admin)->post(route('admin.pa.upload'), [
            'file' => UploadedFile::fake()->createWithContent('pa-large.csv', implode("\n", $lines)),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', fn (string $status) => str_contains($status, 'masuk antrean pemrosesan'));
        Bus::assertDispatched(ProcessPaUpload::class);
        $this->assertDatabaseCount('pa_orders', 0);
        $this->assertSame('queued', UploadBatch::firstOrFail()->status);
    }

    public function test_upload_service_reads_rows_across_chunk_boundary(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $rows = [];
        for ($index = 1; $index <= 501; $index++) {
            $rows[] = ["PA-CHUNK-{$index}", "CUST-{$index}", "Pelanggan {$index}", '081234567890', "Jalan {$index}", 'Bandung', 'Coblong', 'Dago', '2026-09-20'];
        }

        $file = $this->csvFile($rows);
        $path = $file->store('upload-sources', 'local');
        $batch = UploadBatch::create([
            'file_name' => 'pa-chunk.csv',
            'uploaded_by' => $admin->id,
            'uploaded_at' => now(),
            'status' => 'queued',
        ]);

        $result = app(PaUploadService::class)->process($batch, $path);

        $this->assertSame(501, $result['total']);
        $this->assertSame(501, $result['success']);
        $this->assertDatabaseHas('pa_orders', [
            'pa_number' => 'PA-CHUNK-501',
            'id_pln' => 'PLN-TEST',
        ]);
        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_admin_can_view_upload_history_and_download_error_report(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $reportPath = 'upload-reports/batch-history-test.txt';
        Storage::disk('local')->put($reportPath, 'Baris 3: nomor PA sudah terdaftar.');
        $batch = UploadBatch::create([
            'file_name' => 'data-pa.xlsx',
            'uploaded_by' => $admin->id,
            'uploaded_at' => now(),
            'total_rows' => 3,
            'success_rows' => 2,
            'failed_rows' => 1,
            'error_report_path' => $reportPath,
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pa.upload-history'))
            ->assertOk()
            ->assertSee('data-pa.xlsx')
            ->assertSee('Unduh error');

        $this->actingAs($admin)
            ->get(route('admin.pa.upload-history.errors', $batch))
            ->assertOk()
            ->assertDownload('laporan-error-upload-' . $batch->id . '.txt');

        $petugas = $this->userWithRole('petugas');
        $this->actingAs($petugas)
            ->get(route('admin.pa.upload-history'))
            ->assertForbidden();
        $this->actingAs($petugas)
            ->get(route('admin.pa.upload-history.errors', $batch))
            ->assertForbidden();
    }

    public function test_duplicate_pa_number_is_rejected_without_modifying_existing_pa(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $region = Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        PaOrder::factory()->create([
            'pa_number' => 'PA-DUPLICATE',
            'region_id' => $region->id,
            'customer_name' => 'Nama Lama',
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
        ]);
        $file = $this->csvFile([
            ['PA-DUPLICATE', 'CUST-OLD', 'Pelanggan Lama', '081234567890', 'Jl. Lama', 'Bandung', 'Coblong', 'Dago', '2026-09-20'],
            ['PA-NEW', 'CUST-NEW', 'Pelanggan Baru', '081234567891', 'Jl. Baru', 'Bandung', 'Coblong', 'Dago', '2026-09-20'],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.pa.upload'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', '1 dari 2 baris berhasil diimpor. 0 diperbarui, 1 gagal.');
        $this->assertDatabaseCount('pa_orders', 2);
        $this->assertDatabaseHas('pa_orders', ['pa_number' => 'PA-NEW']);
        $this->assertDatabaseHas('pa_orders', [
            'pa_number' => 'PA-DUPLICATE',
            'customer_name' => 'Nama Lama',
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
        ]);
        $this->assertDatabaseMissing('pa_orders', [
            'pa_number' => 'PA-DUPLICATE',
            'customer_name' => 'Pelanggan Lama',
        ]);

        $batch = UploadBatch::latest('id')->firstOrFail();
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertSame("upload-reports/batch-{$batch->id}.txt", $batch->error_report_path);
        Storage::disk('local')->assertExists($batch->error_report_path);
        $this->assertStringContainsString('nomor PA PA-DUPLICATE sudah terdaftar', Storage::disk('local')->get($batch->error_report_path));
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::create(['name' => $roleName]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /** @param array<int, array<int, string>> $rows */
    private function csvFile(array $rows): UploadedFile
    {
        $lines = [
            'pa_number,customer_id,id_pln,customer_name,contact_phone,address,kabupaten_kota,kecamatan,kelurahan,pa_date',
        ];

        foreach ($rows as $row) {
            array_splice($row, 2, 0, ['PLN-TEST']);
            $lines[] = implode(',', $row);
        }

        return UploadedFile::fake()->createWithContent('pa.csv', implode("\n", $lines));
    }
}
