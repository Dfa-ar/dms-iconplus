<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Blueprint 14: "Data pelanggan (nama, alamat, kontak) bersifat pribadi
 * ... sembunyikan sebagian nomor kontak bila role tidak membutuhkannya."
 *
 * Yang benar-benar butuh nomor kontak utuh untuk kerja lapangan: admin
 * (koordinasi) dan petugas (menghubungi pelanggan). Admin adalah satu
 * role yang memegang seluruh wewenang operasional dan konfigurasi.
 * user/role/SLA/audit log (Blueprint bagian 4) -- keduanya tidak perlu
 * nomor utuh, jadi disamarkan.
 *
 * [ASUMSI] Aturan "role mana yang butuh" belum ada di blueprint secara
 * eksplisit untuk kontak, jadi dipetakan mengikuti definisi tanggung
 * jawab tiap role di bagian 4. Sesuaikan array $rolesWithFullContact
 * kalau pembimbing lapangan punya aturan lain.
 */
class PaOrderResource extends JsonResource
{
    /** @var string[] */
    private array $rolesWithFullContact = ['admin', 'petugas'];

    public function toArray(Request $request): array
    {
        $roleName = $request->user()?->role?->name;
        $showFullContact = in_array($roleName, $this->rolesWithFullContact, true);

        return [
            'id' => $this->id,
            'pa_number' => $this->pa_number,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'contact_phone' => $showFullContact
                ? $this->contact_phone
                : $this->maskPhone($this->contact_phone),
            'address' => $this->address,
            'region' => $this->whenLoaded('region'),
            'pa_date' => $this->pa_date?->format('Y-m-d'),
            'current_status' => $this->current_status,
            'current_officer' => $this->whenLoaded('currentOfficer'),
            'assigned_date' => $this->assigned_date?->format('Y-m-d'),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'kendala_reason' => $this->whenLoaded('kendalaReason'),
            'notes' => $this->notes,
            'aging' => $this->aging,
            'evidences' => $this->whenLoaded('evidences'),
            'status_logs' => $this->whenLoaded('statusLogs'),
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 6) {
            return $phone;
        }

        $visibleStart = substr($phone, 0, 4);
        $visibleEnd = substr($phone, -2);
        $maskedLength = max(strlen($phone) - 6, 3);

        return $visibleStart.str_repeat('*', $maskedLength).$visibleEnd;
    }
}
