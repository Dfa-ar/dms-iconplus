<?php

namespace App\Services;

use App\Models\PaOrder;
use App\Models\SlaSetting;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menghitung warna/prioritas aging berdasarkan ambang batas di tabel
 * sla_settings (bukan hardcode), sesuai catatan [ASUMSI] di blueprint —
 * begitu nilainya dikonfirmasi ke pembimbing lapangan, tinggal diubah lewat
 * konfigurasi SLA admin, tidak perlu ubah kode.
 */
class AgingCalculator
{
    public const COLOR_GREEN = 'green';

    public const COLOR_YELLOW = 'yellow';

    public const COLOR_ORANGE = 'orange';

    public const COLOR_RED = 'red';

    protected SlaSetting $settings;

    public function __construct(?SlaSetting $settings = null)
    {
        $this->settings = $settings ?? SlaSetting::current();
    }

    public function colorFor(int $agingDays): string
    {
        return match (true) {
            $agingDays <= $this->settings->aging_green_max => self::COLOR_GREEN,
            $agingDays <= $this->settings->aging_yellow_max => self::COLOR_YELLOW,
            $agingDays <= $this->settings->aging_orange_max => self::COLOR_ORANGE,
            default => self::COLOR_RED,
        };
    }

    public function labelFor(string $color): string
    {
        return match ($color) {
            self::COLOR_GREEN => 'Aman',
            self::COLOR_YELLOW => 'Perlu Dipantau',
            self::COLOR_ORANGE => 'Mendekati SLA',
            self::COLOR_RED => 'Prioritas Tinggi',
            default => '-',
        };
    }

    // Ringkasan lengkap untuk satu PA — inilah yang dipanggil dari
    // AgingReportController / Control Tower, bukan logic mentah di Model.
    public function forPaOrder(PaOrder $paOrder): array
    {
        $days = $paOrder->aging;
        $color = $this->colorFor($days);

        return [
            'days' => $days,
            'color' => $color,
            'label' => $this->labelFor($color),
            'is_over_sla' => $paOrder->isOverSla($this->settings->sla_days),
        ];
    }

    // Query builder untuk halaman "PA Prioritas" — PA belum DONE, diurutkan
    // dari yang paling lama umurnya.
    public function priorityQuery(): Builder
    {
        return PaOrder::query()
            ->unresolved()
            ->orderBy('pa_date'); // paling lama duluan = aging paling besar
    }

    public function overSlaQuery()
    {
        return $this->priorityQuery()->olderThan($this->settings->sla_days);
    }
}
