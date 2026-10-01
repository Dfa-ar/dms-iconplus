<?php

namespace App\Console\Commands;

use App\Models\Officer;
use App\Models\PaOrder;
use App\Services\WhatsappReminderService;
use Illuminate\Console\Command;

class SendWaOverdueReminderCommand extends Command
{
    protected $signature = 'reminders:wa-overdue {--days=2} {--region-id=}';

    protected $description = 'Kirim reminder WhatsApp untuk PA yang sudah terlewat target assigned/overdue';

    public function handle(WhatsappReminderService $service): int
    {
        $days = max(1, (int) $this->option('days'));
        $regionId = $this->option('region-id');

        $query = Officer::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->whereHas('currentPaOrders', function ($q) use ($days) {
                $q->whereIn('current_status', [PaOrder::STATUS_ASSIGNED, PaOrder::STATUS_ON_PROGRESS])
                    ->whereDate('assigned_date', '<=', now()->subDays($days)->toDateString());
            });

        if ($regionId) {
            $query->where('region_id', $regionId);
        }

        $officers = $query->with(['user', 'region'])->get();
        $sent = 0;

        foreach ($officers as $officer) {
            $target = max(1, (int) ($officer->daily_target ?? 20));
            $status = $service->send(
                $officer,
                $service->buildDefaultMessage($officer, $officer->region?->kabupaten_kota, $target),
                $officer->region?->kabupaten_kota,
                $target
            );

            if ($status !== null) {
                $sent++;
            }
        }

        $this->info("Reminder WhatsApp dikirim ke {$sent} petugas.");

        return self::SUCCESS;
    }
}
