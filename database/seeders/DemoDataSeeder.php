<?php

namespace Database\Seeders;

use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Mengisi data dummy yang saling konsisten (officer, PA, assignment, status log,
 * evidence) supaya dashboard Control Tower & Laporan Aging langsung terisi
 * saat development. JANGAN dijalankan di database produksi.
 *
 * Jalankan lewat DatabaseSeeder, atau sendiri dengan:
 *   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RegionSeeder::class);

        // 1 akun admin & 1 supervisor untuk login uji coba
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $supervisorRole = Role::firstOrCreate(['name' => 'supervisor']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@idms.test'],
            [
                'name' => 'Admin IDMS',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'supervisor@idms.test'],
            [
                'name' => 'Supervisor IDMS',
                'password' => Hash::make('password'),
                'role_id' => $supervisorRole->id,
                'is_active' => true,
            ]
        );

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        User::firstOrCreate(
            ['email' => 'superadmin@idms.test'],
            [
                'name' => 'Super Admin IDMS',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
                'is_active' => true,
            ]
        );

        // 1 batch upload dummy sebagai sumber data PA
        $batch = UploadBatch::create([
            'file_name' => 'dummy_batch_seed.xlsx',
            'uploaded_by' => $admin->id,
            'uploaded_at' => now(),
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
        ]);

        // Satu petugas demo deterministik agar mudah diuji manual.
        $petugasRole = Role::firstOrCreate(['name' => 'petugas']);
        $petugasUser = User::firstOrCreate(
            ['email' => 'petugas@idms.test'],
            [
                'name' => 'Petugas Demo',
                'password' => Hash::make('password'),
                'role_id' => $petugasRole->id,
                'is_active' => true,
            ]
        );
        $demoRegion = Region::query()->whereNull('kecamatan')->whereNull('kelurahan')->firstOrFail();
        Officer::firstOrCreate(
            ['employee_code' => 'PETUGAS-DEMO'],
            [
                'user_id' => $petugasUser->id,
                'name' => 'Petugas Demo',
                'phone' => '081234567890',
                'region_id' => $demoRegion->id,
                'is_active' => true,
                'daily_target' => 20,
            ]
        );

        // Petugas tambahan untuk simulasi distribusi dan performa tim.
        $officers = Officer::factory()
            ->count(14)
            ->withAccount()
            ->create();

        // Distribusi status dinormalisasi agar jumlah status selalu sama
        // dengan total PA. Jumlah dapat diperkecil saat development.
        $totalPa = max((int) env('DEMO_PA_COUNT', 20000), 100);
        $statusPlan = [
            'unassigned' => (int) round($totalPa * 0.02),
            'assigned' => (int) round($totalPa * 0.03),
            'onProgress' => (int) round($totalPa * 0.35),
            'done' => (int) round($totalPa * 0.40),
            'kendala' => 0,
        ];
        $statusPlan['kendala'] = $totalPa - array_sum($statusPlan);

        foreach ($statusPlan as $state => $count) {
            $paOrders = $state === 'unassigned'
                ? PaOrder::factory()->count($count)->create(['batch_id' => $batch->id])
                : PaOrder::factory()->{$state}()->count($count)->create(['batch_id' => $batch->id]);

            foreach ($paOrders as $paOrder) {
                $this->attachAssignmentAndHistory($paOrder, $officers, $admin->id);
            }
        }

        $batch->update([
            'total_rows' => PaOrder::where('batch_id', $batch->id)->count(),
            'success_rows' => PaOrder::where('batch_id', $batch->id)->count(),
        ]);
    }

    /**
     * Buat assignment + status_logs + evidence yang konsisten dengan
     * current_status milik satu PA, supaya riwayatnya masuk akal saat dibuka.
     */
    private function attachAssignmentAndHistory(PaOrder $paOrder, $officers, int $adminId): void
    {
        if ($paOrder->current_status === PaOrder::STATUS_UNASSIGNED) {
            \App\Models\StatusLog::factory()->create([
                'pa_id' => $paOrder->id,
                'from_status' => null,
                'to_status' => PaOrder::STATUS_UNASSIGNED,
                'changed_by' => $adminId,
                'changed_at' => $paOrder->pa_date,
            ]);

            return;
        }

        $officer = $paOrder->currentOfficer ?? $officers->random();
        if (! $paOrder->current_officer_id) {
            $paOrder->update(['current_officer_id' => $officer->id]);
        }

        \App\Models\Assignment::factory()->create([
            'pa_id' => $paOrder->id,
            'officer_id' => $officer->id,
            'assign_date' => $paOrder->assigned_date ?? $paOrder->pa_date,
            'assigned_by' => $adminId,
            'source' => 'auto',
        ]);

        \App\Models\StatusLog::factory()->create([
            'pa_id' => $paOrder->id,
            'from_status' => PaOrder::STATUS_UNASSIGNED,
            'to_status' => PaOrder::STATUS_ASSIGNED,
            'changed_by' => $adminId,
            'changed_at' => $paOrder->assigned_date ?? $paOrder->pa_date,
        ]);

        if (in_array($paOrder->current_status, [PaOrder::STATUS_ON_PROGRESS, PaOrder::STATUS_DONE, PaOrder::STATUS_KENDALA], true)) {
            \App\Models\StatusLog::factory()->create([
                'pa_id' => $paOrder->id,
                'from_status' => PaOrder::STATUS_ASSIGNED,
                'to_status' => PaOrder::STATUS_ON_PROGRESS,
                'changed_by' => $officer->user_id ?? $adminId,
                'changed_at' => $paOrder->started_at ?? now(),
            ]);
        }

        if ($paOrder->current_status === PaOrder::STATUS_DONE) {
            \App\Models\StatusLog::factory()->create([
                'pa_id' => $paOrder->id,
                'from_status' => PaOrder::STATUS_ON_PROGRESS,
                'to_status' => PaOrder::STATUS_DONE,
                'changed_by' => $officer->user_id ?? $adminId,
                'changed_at' => $paOrder->completed_at ?? now(),
            ]);

            \App\Models\Evidence::factory()->create([
                'pa_id' => $paOrder->id,
                'type' => 'serah_terima',
                'uploaded_by' => $officer->user_id ?? $adminId,
            ]);
        }

        if ($paOrder->current_status === PaOrder::STATUS_KENDALA) {
            \App\Models\StatusLog::factory()->create([
                'pa_id' => $paOrder->id,
                'from_status' => PaOrder::STATUS_ON_PROGRESS,
                'to_status' => PaOrder::STATUS_KENDALA,
                'changed_by' => $officer->user_id ?? $adminId,
                'kendala_reason_id' => $paOrder->kendala_reason_id,
                'note' => $paOrder->notes,
                'changed_at' => now(),
            ]);
        }
    }
}
