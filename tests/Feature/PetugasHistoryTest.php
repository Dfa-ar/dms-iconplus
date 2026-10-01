<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use App\Models\QcRejectReason;
use App\Models\KendalaReason;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PetugasHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_without_officer_profile_sees_empty_history(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_DONE,
            'current_officer_id' => null,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('petugas.tasks.history'));

        $response->assertOk();
        $response->assertViewHas('history', function ($history) {
            return $history->count() === 0;
        });
    }

    public function test_petugas_cannot_complete_pa_that_belongs_to_another_officer(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $owner = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        $otherUser = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        \App\Models\Officer::factory()->create([
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $otherOfficer = \App\Models\Officer::factory()->create([
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ASSIGNED,
            'current_officer_id' => $otherOfficer->id,
            'assigned_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($owner)->post(route('petugas.tasks.complete', $paOrder), []);

        $response->assertForbidden();
    }

    public function test_petugas_profile_page_is_accessible(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'name' => 'Petugas Profil',
            'email' => 'profil@idms.test',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        $officer = \App\Models\Officer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Petugas Profil',
            'employee_code' => 'PTG-001',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('petugas.profile'));

        $response->assertOk();
        $response->assertSee('Petugas Profil');
        $response->assertSee('PTG-001');
        $response->assertSee($officer->region?->kabupaten_kota ?? 'Wilayah');
    }

    public function test_petugas_is_redirected_to_their_task_dashboard_after_login(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'email' => 'petugas@idms.test',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        \App\Models\Officer::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->withSession(['url.intended' => route('admin.accounts.index')])
            ->from(route('login'))
            ->post(route('login.submit'), [
            'identifier' => 'petugas@idms.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('petugas.tasks.index'));
    }

    public function test_authenticated_petugas_reopening_root_or_login_returns_to_tasks(): void
    {
        Role::query()->firstOrCreate(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);

        \App\Models\Officer::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/')->assertRedirect(route('petugas.tasks.index'));
        $this->get(route('login'))->assertRedirect(route('petugas.tasks.index'));
    }

    public function test_inactive_user_cannot_login(): void
    {
        Role::query()->create(['name' => 'petugas']);

        $user = User::factory()->create([
            'email' => 'inactive@idms.test',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.submit'), [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $identifier = 'rate-limit-'.uniqid().'@idms.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.submit'), [
                    'identifier' => $identifier,
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post(route('login.submit'), [
                'identifier' => $identifier,
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_petugas_cannot_complete_task_before_close_icrm_checklist(): void
    {
        Storage::fake('public');
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
            'assigned_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.complete', $paOrder), [
                'receiver_name' => 'Penerima Test',
            ])
            ->assertRedirect(route('petugas.tasks.show', $paOrder))
            ->assertSessionHasErrors('close_icrm_step');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
        ]);
        $this->assertDatabaseCount('evidences', 0);
    }

    public function test_petugas_can_record_close_icrm_wizard_progress(): void
    {
        Storage::fake('public');
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
        ]);

        $this->actingAs($user)
            ->get(route('petugas.tasks.show', $paOrder))
            ->assertOk()
            ->assertSee('Langkah 1 dari 6')
            ->assertSee('Foto APD lengkap sebelum bekerja')
            ->assertSee('K3 Safety Final')
            ->assertSee('BA Pengambilan Perangkat');

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.close-icrm', $paOrder), [
                'step_number' => 1,
                'foto_k3_awal' => UploadedFile::fake()->create('k3-awal.jpg', 10, 'image/jpeg'),
            ])
            ->assertRedirect(route('petugas.tasks.show', $paOrder));

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'close_icrm_step' => 'STEP_1',
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
        ]);
        $this->assertDatabaseHas('evidences', [
            'pa_id' => $paOrder->id,
            'type' => 'k3_awal',
        ]);

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.close-icrm', $paOrder), ['step_number' => 3])
            ->assertRedirect(route('petugas.tasks.show', $paOrder))
            ->assertSessionHasErrors('step_number');
    }

    public function test_qc_rejects_unreadable_sn_based_on_photo_review(): void
    {
        Storage::fake('public');
        $adminRole = Role::query()->create(['name' => 'admin']);
        $petugasRole = Role::query()->create(['name' => 'petugas']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $petugas = User::factory()->create(['role_id' => $petugasRole->id, 'is_active' => true]);
        $reason = QcRejectReason::create([
            'code' => 'sn_ont_tidak_terbaca',
            'label' => 'SN ONT tidak terbaca',
        ]);
        $region = \App\Models\Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $petugas->id, 'region_id' => $region->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_officer_id' => $officer->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'close_icrm_step' => 'STEP_1',
        ]);

        $this->actingAs($petugas)
            ->post(route('petugas.tasks.close-icrm', $paOrder), [
                'step_number' => 2,
                'foto_ont_depan' => UploadedFile::fake()->create('ont-depan.jpg', 10, 'image/jpeg'),
                'foto_ont_belakang_sn' => UploadedFile::fake()->create('ont-sn.jpg', 10, 'image/jpeg'),
                'sn_ont_readable' => '1',
            ])
            ->assertRedirect(route('petugas.tasks.show', $paOrder));

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'serial_number_ont' => null,
        ]);
        $this->assertDatabaseHas('evidences', ['pa_id' => $paOrder->id, 'type' => 'ont_belakang_sn']);

        $this->actingAs($admin)
            ->post(route('admin.qc.review', $paOrder), [
                'decision' => 'approve',
                'reason_id' => $reason->id,
                'qc_checks' => array_merge(
                    array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true),
                    ['sn_ont' => '0'],
                ),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'qc_status' => PaOrder::QC_STATUS_REJECTED,
            'current_status' => PaOrder::QC_STATUS_REJECTED,
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'from_status' => PaOrder::STATUS_ON_PROGRESS,
            'to_status' => PaOrder::QC_STATUS_REJECTED,
        ]);
    }

    public function test_missing_kwh_requires_note_without_photo(): void
    {
        Storage::fake('public');
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $region = \App\Models\Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'id_pln' => 'PLN-TEST-004',
            'current_officer_id' => $officer->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'close_icrm_step' => 'STEP_3',
        ]);

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.close-icrm', $paOrder), [
                'step_number' => 4,
                'id_pln_confirmed' => '1',
                'kwh_status' => 'TIDAK_DITEMUKAN',
            ])
            ->assertRedirect(route('petugas.tasks.show', $paOrder))
            ->assertSessionHasErrors('kwh_note');

        $this->assertDatabaseMissing('pa_orders', [
            'id' => $paOrder->id,
            'close_icrm_step' => 'STEP_4',
        ]);

        $this->actingAs($user)
            ->post(route('petugas.tasks.close-icrm', $paOrder), [
                'step_number' => 4,
                'id_pln_confirmed' => '1',
                'kwh_status' => 'TIDAK_DITEMUKAN',
                'kwh_note' => 'Tidak ditemukan pada lokasi meter.',
            ])
            ->assertRedirect(route('petugas.tasks.show', $paOrder));

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'id_pln_confirmed' => 1,
            'kwh_status' => 'TIDAK_DITEMUKAN',
            'kwh_note' => 'Tidak ditemukan pada lokasi meter.',
            'close_icrm_step' => 'STEP_4',
        ]);
        $this->assertDatabaseMissing('evidences', ['pa_id' => $paOrder->id, 'type' => 'kwh_meter']);
    }

    public function test_cable_step_rejects_used_or_out_of_capacity_ports(): void
    {
        Storage::fake('public');
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $region = \App\Models\Region::create([
            'kabupaten_kota' => 'Bandung',
            'kecamatan' => 'Coblong',
            'kelurahan' => 'Dago',
        ]);
        $fatPoint = \App\Models\FatPoint::create([
            'kode_fat' => 'FAT-CLOSE-01',
            'nama_fat' => 'FAT Dago',
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $splitter = \App\Models\Splitter::create([
            'kode_splitter' => 'SPL-CLOSE-01',
            'nama_splitter' => 'Splitter Dago',
            'fat_point_id' => $fatPoint->id,
            'capacity_port' => 4,
            'total_port' => 4,
            'is_active' => true,
        ]);
        PaOrder::factory()->create([
            'region_id' => $region->id,
            'splitter_id' => $splitter->id,
            'port_number' => 1,
            'current_status' => PaOrder::STATUS_DONE,
        ]);
        $paOrder = PaOrder::factory()->create([
            'region_id' => $region->id,
            'current_officer_id' => $officer->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'close_icrm_step' => 'STEP_2',
        ]);
        $payload = [
            'step_number' => 3,
            'kabel_panjang_meter' => '0',
            'foto_fat_terdekat' => UploadedFile::fake()->create('fat.jpg', 10, 'image/jpeg'),
            'fat_point_id' => $fatPoint->id,
            'splitter_id' => $splitter->id,
        ];

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.close-icrm', $paOrder), $payload + ['port_number' => 1])
            ->assertRedirect(route('petugas.tasks.show', $paOrder))
            ->assertSessionHasErrors('port_number');

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.close-icrm', $paOrder), $payload + ['port_number' => 5])
            ->assertRedirect(route('petugas.tasks.show', $paOrder))
            ->assertSessionHasErrors('port_number');

        $this->actingAs($user)
            ->post(route('petugas.tasks.close-icrm', $paOrder), $payload + ['port_number' => 2])
            ->assertRedirect(route('petugas.tasks.show', $paOrder));

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'kabel_panjang_meter' => 0,
            'fat_point_id' => $fatPoint->id,
            'splitter_id' => $splitter->id,
            'port_number' => 2,
            'close_icrm_step' => 'STEP_3',
        ]);
    }

    public function test_pa_cannot_be_completed_without_signed_pickup_document(): void
    {
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
            'close_icrm_step' => 'STEP_5',
        ]);

        $this->actingAs($user)
            ->from(route('petugas.tasks.show', $paOrder))
            ->post(route('petugas.tasks.complete', $paOrder), ['receiver_name' => 'Penerima Test'])
            ->assertRedirect(route('petugas.tasks.show', $paOrder))
            ->assertSessionHasErrors('ba_pengambilan_perangkat');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'close_icrm_step' => 'STEP_5',
        ]);
    }

    public function test_signed_pickup_document_completes_pa_and_queues_qc(): void
    {
        Storage::fake('public');
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
            'close_icrm_step' => 'STEP_5',
        ]);

        $this->actingAs($user)
            ->post(route('petugas.tasks.complete', $paOrder), [
                'receiver_name' => 'Pelanggan Penerima',
                'ba_pengambilan_perangkat' => UploadedFile::fake()->create('ba-pengambilan.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect(route('petugas.tasks.index'));

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
            'qc_status' => PaOrder::QC_STATUS_PENDING,
            'close_icrm_step' => 'STEP_6',
        ]);
        $this->assertDatabaseHas('evidences', [
            'pa_id' => $paOrder->id,
            'type' => 'ba_pengambilan_perangkat',
            'receiver_name' => 'Pelanggan Penerima',
        ]);
        $this->assertDatabaseHas('status_logs', [
            'pa_id' => $paOrder->id,
            'from_status' => PaOrder::STATUS_ON_PROGRESS,
            'to_status' => PaOrder::BLUEPRINT_STATUS_PENDING_QC,
        ]);
    }

    public function test_kendala_notes_are_required_only_for_lainnya(): void
    {
        Role::query()->create(['name' => 'petugas']);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'petugas')->value('id'),
            'is_active' => true,
        ]);
        $officer = \App\Models\Officer::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $paOrder = PaOrder::factory()->create([
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
            'current_officer_id' => $officer->id,
        ]);
        $reason = KendalaReason::create(['label' => 'Lainnya', 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('petugas.tasks.kendala', $paOrder), [
                'kendala_reason_id' => $reason->id,
            ])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('pa_orders', [
            'id' => $paOrder->id,
            'current_status' => PaOrder::STATUS_ON_PROGRESS,
        ]);
    }
}
