<?php

namespace Database\Seeders;

use App\Models\Evidence;
use App\Models\Officer;
use App\Models\PaOrder;
use App\Models\Region;
use App\Models\Role;
use App\Models\UploadBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $totalPa = (int) env('DEMO_PA_COUNT', 3000);
        $totalPa = max(300, min($totalPa, 5000));

        $this->resetDemoData();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin = User::firstOrCreate(
            ['email' => 'admin@idms.test'],
            [
                'name' => 'Admin IDMS',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        $batch = UploadBatch::create([
            'file_name' => 'dummy_batch_seed.xlsx',
            'uploaded_by' => $admin->id,
            'uploaded_at' => now(),
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
        ]);

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

        $regions = Region::query()
            ->whereNull('kecamatan')
            ->whereNull('kelurahan')
            ->orderBy('kabupaten_kota')
            ->get();
        $regionalEquipment = $this->ensureQcNetworkInventory($regions);

        $officers = collect();
        foreach ($regions as $region) {
            $regionalOfficers = Officer::query()
                ->where('region_id', $region->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(3)
                ->get();

            for ($number = $regionalOfficers->count() + 1; $number <= 3; $number++) {
                $regionalOfficers->push(Officer::firstOrCreate(
                    ['employee_code' => sprintf('DEMO-%02d-%d', $region->id, $number)],
                    [
                        'name' => sprintf('Petugas Demo %d-%d', $region->id, $number),
                        'phone' => '08' . str_pad((string) ($region->id * 100 + $number), 10, '0', STR_PAD_LEFT),
                        'region_id' => $region->id,
                        'is_active' => true,
                        'daily_target' => 20,
                    ]
                ));
            }

            $officers = $officers->merge($regionalOfficers);
        }

        Officer::firstOrCreate(
            ['employee_code' => 'PETUGAS-DEMO'],
            [
                'user_id' => $petugasUser->id,
                'name' => 'Petugas Demo',
                'phone' => '081234567890',
                'region_id' => $regions->first()->id,
                'is_active' => true,
                'daily_target' => 20,
            ]
        );

        $statuses = [
            'unassigned' => 0.04,
            'assigned' => 0.08,
            'onProgress' => 0.24,
            'done' => 0.62,
            'kendala' => 0.02,
        ];

        $byStatus = [];
        foreach ($statuses as $status => $share) {
            $byStatus[$status] = (int) round($totalPa * $share);
        }
        $byStatus['done'] += $totalPa - array_sum($byStatus);

        $allPa = collect();
        foreach ($regions as $regionIndex => $region) {
            $perRegion = (int) floor($totalPa / $regions->count());
            if ($regionIndex === 0) {
                $perRegion += $totalPa % $regions->count();
            }

            $regionCounts = [
                'unassigned' => max(1, (int) round($byStatus['unassigned'] / $regions->count())),
                'assigned' => max(1, (int) round($byStatus['assigned'] / $regions->count())),
                'onProgress' => max(1, (int) round($byStatus['onProgress'] / $regions->count())),
                'done' => max(1, (int) round($byStatus['done'] / $regions->count())),
                'kendala' => max(1, (int) round($byStatus['kendala'] / $regions->count())),
            ];

            if ($regionIndex === 0) {
                $regionCounts['done'] += $perRegion - array_sum($regionCounts);
            }

            $regionPaIndex = 0;
            foreach ($regionCounts as $state => $count) {
                $paOrders = $state === 'unassigned'
                    ? PaOrder::factory()->count($count)->create([
                        'batch_id' => $batch->id,
                        'region_id' => $region->id,
                    ])
                    : PaOrder::factory()->{$state}()->count($count)->create([
                        'batch_id' => $batch->id,
                        'region_id' => $region->id,
                    ]);

                foreach ($paOrders as $paOrder) {
                    $this->attachAssignmentAndHistory($paOrder, $officers, $admin->id, $regionalEquipment, $regionPaIndex++);
                    $allPa->push($paOrder);
                }
            }
        }

        $batch->update([
            'total_rows' => $allPa->count(),
            'success_rows' => $allPa->count(),
        ]);
    }

    /**
     * Buat assignment + status_logs + evidence yang konsisten dengan
     * current_status milik satu PA, supaya riwayatnya masuk akal saat dibuka.
     */
    private function attachAssignmentAndHistory(PaOrder $paOrder, $officers, int $adminId, array $regionalEquipment, int $regionPaIndex): void
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

        $regionalOfficers = $officers->where('region_id', $paOrder->region_id);
        $officer = $regionalOfficers->isNotEmpty() ? $regionalOfficers->random() : $officers->random();

        if ($paOrder->current_officer_id !== $officer->id) {
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

            $paOrder->update([
                'id_pln' => 'PLN-' . strtoupper(Str::random(8)),
                'id_pln_confirmed' => true,
                'kwh_status' => 'ADA',
                'kwh_note' => 'Normal',
                'kabel_panjang_meter' => 15.5,
                'serial_number_ont' => 'ONT-' . strtoupper(Str::random(8)),
                'sn_ont_readable' => true,
                'fat_point_id' => $regionalEquipment[$paOrder->region_id]['fat_point_id'],
                'splitter_id' => $regionalEquipment[$paOrder->region_id]['splitter_ids'][intdiv($regionPaIndex % 144, 24)],
                'port_number' => ($regionPaIndex % 24) + 1,
                'qc_status' => PaOrder::QC_STATUS_PENDING,
                'qc_checklist' => array_fill_keys(array_keys(PaOrder::blueprintQcChecklist()), true),
            ]);

            $this->generateQcReadyEvidence($paOrder, $officer->user_id ?? $adminId);
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

    private function generateQcReadyEvidence(PaOrder $paOrder, int $uploadedBy): void
    {
        $types = [
            'k3_awal',
            'ont_depan',
            'ont_belakang_sn',
            'fat_terdekat',
            'kwh_meter',
            'k3_akhir',
            'ba_pengambilan_perangkat',
        ];

        foreach ($types as $type) {
            if ($paOrder->evidences()->where('type', $type)->exists()) {
                continue;
            }

            $slug = Str::slug($type, '-');
            $fileName = sprintf('%s-%s.svg', $slug, Str::random(6));
            $filePath = 'evidence/' . $paOrder->pa_number . '/' . $fileName;

            Storage::disk('public')->put($filePath, $this->makeEvidenceSvg($paOrder, $type));

            Evidence::create([
                'pa_id' => $paOrder->id,
                'type' => $type,
                'file_path' => $filePath,
                'uploaded_by' => $uploadedBy,
                'uploaded_at' => now(),
                'receiver_name' => $type === 'ba_pengambilan_perangkat' ? $paOrder->customer_name : null,
                'pickup_time' => now(),
            ]);
        }
    }

    private function makeEvidenceSvg(PaOrder $paOrder, string $type): string
    {
        $labelMap = [
            'k3_awal' => 'K3 Awal',
            'ont_depan' => 'ONT Depan',
            'ont_belakang_sn' => 'ONT Belakang SN',
            'fat_terdekat' => 'FAT Terdekat',
            'kwh_meter' => 'KWH Meter',
            'k3_akhir' => 'K3 Akhir',
            'ba_pengambilan_perangkat' => 'BA Pengambilan Perangkat',
        ];

        $label = $labelMap[$type] ?? 'Bukti QC';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900" role="img" aria-label="{$label}">
  <rect width="1200" height="900" fill="#e0f2fe"/>
  <rect x="90" y="90" width="1020" height="720" rx="28" fill="#ffffff" opacity="0.9"/>
  <rect x="150" y="170" width="900" height="420" rx="25" fill="#dbeafe"/>
  <rect x="150" y="620" width="420" height="90" rx="18" fill="#0f766e"/>
  <text x="190" y="676" fill="#ffffff" font-size="32" font-family="Arial" font-weight="700">{$label}</text>
  <text x="150" y="120" fill="#0f172a" font-size="34" font-family="Arial" font-weight="700">PA: {$paOrder->pa_number}</text>
  <text x="150" y="150" fill="#334155" font-size="20" font-family="Arial">Customer: {$paOrder->customer_name}</text>
  <text x="770" y="220" fill="#0f172a" font-size="28" font-family="Arial" font-weight="700">Dummy QC Photo</text>
  <text x="770" y="255" fill="#334155" font-size="22" font-family="Arial">Generated for QA approval</text>
</svg>
SVG;
    }

    private function ensureQcNetworkInventory($regions): array
    {
        $regionalEquipment = [];

        foreach ($regions as $region) {
            $fatPoint = \App\Models\FatPoint::firstOrCreate(
                ['kode_fat' => 'DEMO-FAT-' . $region->id],
                [
                    'nama_fat' => 'FAT Demo ' . $region->kabupaten_kota,
                    'region_id' => $region->id,
                    'is_active' => true,
                ]
            );

            $splitterIds = [];
            for ($number = 1; $number <= 6; $number++) {
                $splitter = \App\Models\Splitter::firstOrCreate(
                    ['kode_splitter' => 'DEMO-SPL-' . $region->id . '-' . $number],
                    [
                        'nama_splitter' => 'Splitter Demo ' . $number . ' - ' . $region->kabupaten_kota,
                        'fat_point_id' => $fatPoint->id,
                        'total_port' => 24,
                        'is_active' => true,
                    ]
                );
                $splitterIds[] = $splitter->id;
            }

            $regionalEquipment[$region->id] = [
                'fat_point_id' => $fatPoint->id,
                'splitter_ids' => $splitterIds,
            ];
        }

        return $regionalEquipment;
    }

    private function resetDemoData(): void
    {
        foreach (['bast_items', 'bast_documents', 'status_logs', 'assignments', 'evidences', 'pa_orders', 'upload_batches'] as $table) {
            \Illuminate\Support\Facades\DB::table($table)->delete();
        }
    }
}
