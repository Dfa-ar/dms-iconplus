<?php

namespace Tests\Feature;

use App\Models\Officer;
use App\Models\BastDocument;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentAndMonitoringFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_payment_batch_rolls_back_and_removes_uploaded_proof(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_DONE,
            'payment_status' => 'BAST_CREATED',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.batches.store'), [
                'payment_date' => now()->toDateString(),
                'method' => 'BANK_TRANSFER',
                'proof_file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
                'pa_ids' => [$paOrder->id],
                'amounts' => [$paOrder->id => '50000'],
            ])
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHasErrors('pa_ids');

        $this->assertDatabaseCount('payment_batches', 0);
        $this->assertDatabaseCount('payment_items', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_legacy_status_form_cannot_mark_payment_paid_without_batch_proof(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_DONE,
            'payment_status' => 'BAST_CREATED',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.update', $paOrder), ['payment_status' => 'PAID'])
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHasErrors('payment_status');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'payment_status' => 'BAST_CREATED',
            'current_status' => PaOrder::STATUS_DONE,
        ]);
        $this->assertDatabaseCount('payment_batches', 0);
    }

    public function test_legacy_payment_status_correction_creates_audit_log(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_DONE,
            'payment_status' => 'BAST_CREATED',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.update', $paOrder), [
                'payment_status' => 'REJECTED',
                'payment_note' => 'Bukti transaksi perlu diverifikasi.',
            ])
            ->assertRedirect(route('admin.payments.index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment_status_updated',
            'entity' => 'PaOrder',
            'entity_id' => $paOrder->id,
        ]);
    }

    public function test_admin_can_manage_payment_and_monitoring(): void
    {
        Storage::fake('local');
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $region = Region::create([
            'kabupaten_kota' => 'Garut',
            'kecamatan' => 'Tarogong',
            'kelurahan' => 'Tarogong Kidul',
            'parent_group' => 'Garut',
        ]);

        $officer = Officer::factory()->create([
            'user_id' => User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true])->id,
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $bastDocument = BastDocument::create([
            'nomor_bast' => '0001/BAST/GARUT/' . now()->year,
            'region_id' => $region->id,
            'created_by' => $admin->id,
            'tanggal' => now()->toDateString(),
            'status' => 'FINAL',
        ]);

        $paOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-PAY-001',
            'customer_name' => 'Customer Payment',
            'region_id' => $region->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
            'current_officer_id' => $officer->id,
            'qc_status' => 'PASSED',
            'payment_status' => 'BAST_CREATED',
            'bast_document_id' => $bastDocument->id,
        ]);
        $secondPaOrder = PaOrder::factory()->create([
            'pa_number' => 'PA-PAY-002',
            'customer_name' => 'Customer Payment 2',
            'region_id' => $region->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_BAST_ISSUED,
            'current_officer_id' => $officer->id,
            'qc_status' => 'PASSED',
            'payment_status' => 'BAST_CREATED',
            'bast_document_id' => $bastDocument->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Catat pembayaran batch')
            ->assertSee('Total nominal');

        $this->actingAs($admin)
            ->post(route('admin.payments.batches.store'), [
                'payment_date' => now()->toDateString(),
                'method' => 'BANK_TRANSFER',
                'reference_no' => 'TRF-TEST-001',
                'proof_file' => UploadedFile::fake()->create('bukti-transfer.pdf', 20, 'application/pdf'),
                'notes' => 'Pembayaran batch uji.',
                'pa_ids' => [$paOrder->id, $secondPaOrder->id],
                'amounts' => [$paOrder->id => '125000.50', $secondPaOrder->id => '75000'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'payment_status' => 'PAID',
            'current_status' => PaOrder::BLUEPRINT_STATUS_PAID,
        ]);
        $this->assertDatabaseHas('payment_batches', [
            'reference_no' => 'TRF-TEST-001',
            'method' => 'BANK_TRANSFER',
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('payment_items', [
            'pa_id' => $paOrder->id,
            'amount' => '125000.50',
        ]);
        $this->assertDatabaseHas('payment_items', [
            'pa_id' => $secondPaOrder->id,
            'amount' => '75000.00',
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'to_status' => PaOrder::BLUEPRINT_STATUS_PAID,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment_batch_created',
            'entity' => 'PaymentBatch',
        ]);

        $batch = \App\Models\PaymentBatch::firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.payments.batches.proof', $batch))
            ->assertOk();

        $exportResponse = $this->actingAs($admin)
            ->get(route('admin.payments.export'))
            ->assertOk();
        $this->assertStringContainsString('PA-PAY-001', $exportResponse->streamedContent());
        $this->assertStringContainsString('PA-PAY-002', $exportResponse->streamedContent());
        $this->assertStringContainsString('200000.50', $exportResponse->streamedContent());

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Control Tower')
            ->assertSee('Sudah dibayar')
            ->assertSee('BAST diterbitkan');
    }
}
