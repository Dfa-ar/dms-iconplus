<?php

namespace App\Console\Commands;

use App\Models\Evidence;
use App\Models\PaOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateDummyEvidenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dummy:evidence {--pa=} {--count=10} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate dummy photo evidence for PA records in public storage.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $paId = $this->option('pa');
        $count = max(1, (int) $this->option('count'));
        $force = (bool) $this->option('force');

        $query = PaOrder::query();

        if ($paId) {
            $query->where('id', $paId);
        }

        $paOrders = $query->orderBy('pa_date')->limit($count)->get();

        if ($paOrders->isEmpty()) {
            $this->warn('Tidak ada PA yang bisa dibuat dummy foto.');
            return self::SUCCESS;
        }

        $created = 0;

        foreach ($paOrders as $paOrder) {
            $requiredTypes = [
                'k3_awal',
                'ont_depan',
                'ont_belakang_sn',
                'fat_terdekat',
                'kwh_meter',
                'k3_akhir',
                'ba_pengambilan_perangkat',
            ];

            foreach ($requiredTypes as $type) {
                if (! $force && $paOrder->evidences()->where('type', $type)->exists()) {
                    continue;
                }

                $slug = Str::slug($type, '-');
                $fileName = sprintf('%s-%s.svg', $slug, Str::random(6));
                $filePath = 'evidence/' . $paOrder->pa_number . '/' . $fileName;

                Storage::disk('public')->put(
                    $filePath,
                    $this->makeSvg($paOrder, $type)
                );

                $paOrder->evidences()->updateOrCreate(
                    ['pa_id' => $paOrder->id, 'type' => $type],
                    [
                        'file_path' => $filePath,
                        'uploaded_by' => $paOrder->currentOfficer?->user_id ?? null,
                        'uploaded_at' => now(),
                        'receiver_name' => $type === 'ba_pengambilan_perangkat' ? $paOrder->customer_name : null,
                        'pickup_time' => now(),
                    ]
                );

                $created++;
            }
        }

        $this->info("Sukses membuat {$created} dummy foto evidence di storage/public.");

        return self::SUCCESS;
    }

    private function makeSvg(PaOrder $paOrder, string $type): string
    {
        $titleMap = [
            'k3_awal' => 'K3 Awal',
            'ont_depan' => 'ONT Depan',
            'ont_belakang_sn' => 'ONT Belakang SN',
            'fat_terdekat' => 'FAT Terdekat',
            'kwh_meter' => 'KWH Meter',
            'k3_akhir' => 'K3 Akhir',
            'ba_pengambilan_perangkat' => 'BA Pengambilan Perangkat',
        ];

        $label = $titleMap[$type] ?? 'Bukti PA';
        $bgA = '#dbeafe';
        $bgB = '#e0f2fe';
        $accent = '#0f766e';
        $dark = '#0f172a';
        $customerId = $paOrder->customer_id ?: 'DEMO';

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900" role="img" aria-label="{$label}">
          <defs>
            <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0%" stop-color="{$bgA}" />
              <stop offset="100%" stop-color="{$bgB}" />
            </linearGradient>
          </defs>
          <rect width="1200" height="900" fill="url(#bg)"/>
          <rect x="90" y="100" width="1020" height="700" rx="36" fill="#ffffff" opacity="0.9"/>
          <rect x="150" y="170" width="900" height="470" rx="28" fill="#e2e8f0"/>
          <circle cx="260" cy="300" r="120" fill="#93c5fd" opacity="0.9"/>
          <rect x="400" y="220" width="420" height="210" rx="18" fill="#7dd3fc" opacity="0.9"/>
          <rect x="450" y="470" width="320" height="90" rx="18" fill="#0f766e" opacity="0.8"/>
          <rect x="150" y="680" width="520" height="60" rx="14" fill="#0f172a" opacity="0.9"/>
          <text x="190" y="720" fill="#ffffff" font-size="30" font-family="Arial, Helvetica, sans-serif" font-weight="700">{$label}</text>
          <text x="150" y="96" fill="{$dark}" font-size="34" font-family="Arial, Helvetica, sans-serif" font-weight="700">PA: {$paOrder->pa_number}</text>
          <text x="150" y="130" fill="#334155" font-size="23" font-family="Arial, Helvetica, sans-serif">ID Pelanggan: {$customerId}</text>
          <text x="770" y="220" fill="{$accent}" font-size="28" font-family="Arial, Helvetica, sans-serif" font-weight="700">Dummy Photo</text>
          <text x="770" y="255" fill="#334155" font-size="22" font-family="Arial, Helvetica, sans-serif">Generated for QC preview</text>
        </svg>
        SVG;
    }
}
