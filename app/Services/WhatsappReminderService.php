<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Officer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsappReminderService
{
    public function send(Officer $officer, ?string $message = null, ?string $regionName = null, ?int $target = null): ?string
    {
        $user = $officer->user;
        $phone = $this->normalizePhone($officer->phone);
        $finalMessage = $message ?? $this->buildDefaultMessage($officer, $regionName, $target);

        $waUrl = $phone
            ? 'https://wa.me/' . $phone . '?text=' . urlencode($finalMessage)
            : null;

        $record = Notification::create([
            'user_id' => $user?->id ?? $officer->user_id,
            'channel' => 'whatsapp',
            'message' => $finalMessage,
            'status' => $phone ? 'sent' : 'failed',
            'sent_at' => $phone ? now() : null,
        ]);

        $endpoint = config('services.whatsapp.api_url');
        if ($endpoint && $phone) {
            try {
                Http::timeout(15)->post($endpoint, [
                    'to' => $phone,
                    'message' => $finalMessage,
                    'user_id' => $user?->id ?? $officer->user_id,
                    'notification_id' => $record->id,
                ]);

                $record->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Throwable $e) {
                $record->update(['status' => 'failed']);
            }
        }

        return $waUrl;
    }

    public function buildDefaultMessage(Officer $officer, ?string $regionName = null, ?int $target = null): string
    {
        $remaining = max((int) ($target ?? $officer->daily_target ?? 20), 0);
        $region = $regionName ?: ($officer->region?->kabupaten_kota ?? 'wilayah kerja Anda');

        return "📢 Tugas Deaktivasi Hari Ini\n"
            . "Halo {$officer->name}, terdapat {$remaining} PA yang perlu dikerjakan hari ini.\n"
            . "Silakan akses tugas melalui sistem Deaktivasi ICONNET.\n"
            . "Target hari ini: {$remaining} PA\n"
            . "Wilayah: {$region}\n"
            . "Link: " . url('/my/tasks');
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (! Str::startsWith($digits, '62')) {
            return '62' . $digits;
        }

        return $digits;
    }
}
