<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaOrder extends Model
{
    use HasFactory;

    // Nama tabel tidak mengikuti konvensi jamak default Eloquent
    protected $table = 'pa_orders';

    protected $fillable = [
        'pa_number', 'customer_id', 'id_pln', 'id_pln_confirmed', 'customer_name', 'contact_phone', 'address',
        'region_id', 'kantor_perwakilan_id', 'fat_point_id', 'splitter_id', 'port_number', 'pa_date',
        'current_status', 'current_officer_id', 'assigned_date', 'started_at',
        'completed_at', 'kendala_reason_id', 'notes', 'batch_id', 'qc_status',
        'qc_reject_reason_id', 'qc_note', 'bast_document_id', 'payment_status',
        'serial_number_ont', 'sn_ont_readable', 'kabel_panjang_meter', 'kwh_status', 'kwh_note', 'close_icrm_step',
        'kondisi_ont', 'adaptor', 'qc_checklist',
    ];

    protected $casts = [
        'pa_date' => 'date',
        'assigned_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'qc_checklist' => 'array',
        'sn_ont_readable' => 'boolean',
        'id_pln_confirmed' => 'boolean',
        'kabel_panjang_meter' => 'decimal:2',
    ];

    // Nilai current_status mengikuti enum di migration
    public const STATUS_UNASSIGNED = 'UNASSIGNED';
    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_ON_PROGRESS = 'ON_PROGRESS';
    public const STATUS_DONE = 'DONE';
    public const STATUS_KENDALA = 'KENDALA';
    public const STATUS_CLOSE_ICRM = 'CLOSE_ICRM';

    public const QC_STATUS_PENDING = 'PENDING';
    public const QC_STATUS_PASSED = 'PASSED';
    public const QC_STATUS_REJECTED = 'REJECTED';

    public const BLUEPRINT_STATUS_PENDING_QC = 'PENDING_QC';
    public const BLUEPRINT_STATUS_BAST_ISSUED = 'BAST_ISSUED';
    public const BLUEPRINT_STATUS_BAST_VOID = 'BAST_VOID';
    public const BLUEPRINT_STATUS_PAID = 'PAID';

    public static function blueprintStatusOptions(): array
    {
        return [
            self::STATUS_UNASSIGNED => 'Menunggu penugasan',
            self::STATUS_ASSIGNED => 'Ditugaskan',
            self::STATUS_ON_PROGRESS => 'Sedang dikerjakan',
            self::STATUS_DONE => 'Selesai kerja lapangan',
            self::STATUS_KENDALA => 'Kendala',
            self::STATUS_CLOSE_ICRM => 'Close ICRM',
            self::BLUEPRINT_STATUS_PENDING_QC => 'Menunggu QC',
            self::QC_STATUS_PASSED => 'Lulus QC',
            self::QC_STATUS_REJECTED => 'Ditolak QC',
            self::BLUEPRINT_STATUS_BAST_ISSUED => 'BAST diterbitkan',
            self::BLUEPRINT_STATUS_BAST_VOID => 'BAST dibatalkan',
            self::BLUEPRINT_STATUS_PAID => 'Lunas / dibayar',
        ];
    }

    public static function canTransitionFromTo(string $fromStatus, string $toStatus): bool
    {
        $allowed = [
            self::STATUS_UNASSIGNED => [self::STATUS_ASSIGNED, self::STATUS_KENDALA],
            self::STATUS_ASSIGNED => [self::STATUS_ON_PROGRESS, self::STATUS_KENDALA],
            self::STATUS_ON_PROGRESS => [self::STATUS_DONE, self::STATUS_KENDALA, self::STATUS_CLOSE_ICRM, self::BLUEPRINT_STATUS_PENDING_QC],
            self::STATUS_DONE => [self::BLUEPRINT_STATUS_PENDING_QC, self::QC_STATUS_PASSED, self::QC_STATUS_REJECTED, self::STATUS_KENDALA],
            self::STATUS_KENDALA => [self::STATUS_UNASSIGNED, self::STATUS_ASSIGNED, self::STATUS_ON_PROGRESS],
            self::STATUS_CLOSE_ICRM => [self::BLUEPRINT_STATUS_PENDING_QC],
            self::BLUEPRINT_STATUS_PENDING_QC => [self::QC_STATUS_PASSED, self::QC_STATUS_REJECTED],
            self::QC_STATUS_PASSED => [self::BLUEPRINT_STATUS_BAST_ISSUED],
            self::QC_STATUS_REJECTED => [self::STATUS_ASSIGNED, self::STATUS_ON_PROGRESS],
            self::BLUEPRINT_STATUS_BAST_ISSUED => [self::BLUEPRINT_STATUS_PAID, self::BLUEPRINT_STATUS_BAST_VOID],
            self::BLUEPRINT_STATUS_BAST_VOID => [self::BLUEPRINT_STATUS_BAST_ISSUED],
            self::BLUEPRINT_STATUS_PAID => [],
        ];

        return in_array($toStatus, $allowed[$fromStatus] ?? [], true);
    }

    public static function blueprintQcCategories(): array
    {
        return [
            'A' => [
                'label' => 'K3 awal',
                'checks' => ['k3_awal' => 'APD lengkap sebelum pekerjaan'],
            ],
            'B' => [
                'label' => 'ONT dan serial number',
                'checks' => [
                    'ont_depan' => 'Foto ONT tampak depan',
                    'sn_ont' => 'SN ONT terbaca',
                ],
            ],
            'C' => [
                'label' => 'Kabel dan jaringan',
                'checks' => [
                    'kabel' => 'Panjang kabel sesuai',
                    'fat' => 'FAT sesuai dan terbukti foto',
                    'splitter' => 'Splitter sesuai',
                    'port_lock' => 'Port tersedia dan terkunci sesuai',
                ],
            ],
            'D' => [
                'label' => 'ID PLN dan KWH',
                'checks' => ['id_pln_kwh' => 'ID PLN terkonfirmasi dan status/catatan KWH sesuai'],
            ],
            'E' => [
                'label' => 'K3 akhir',
                'checks' => ['k3_akhir' => 'APD lengkap setelah pekerjaan'],
            ],
            'F' => [
                'label' => 'BA Pengambilan',
                'checks' => ['ba_pengambilan' => 'BA ditandatangani pelanggan'],
            ],
        ];
    }

    public static function blueprintQcChecklist(): array
    {
        $checklist = [];

        foreach (self::blueprintQcCategories() as $category => $details) {
            foreach ($details['checks'] as $key => $label) {
                $checklist[$key] = $label;
            }
        }

        return $checklist;
    }

    public static function normalizeQcChecklist(?array $input): array
    {
        $defaults = array_fill_keys(array_keys(self::blueprintQcChecklist()), false);

        if (! is_array($input)) {
            return $defaults;
        }

        foreach ($defaults as $key => $value) {
            $defaults[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $defaults;
    }

    public function qcChecklistPassed(): bool
    {
        $checklist = $this->qc_checklist ?? [];

        foreach (self::blueprintQcChecklist() as $key => $label) {
            if (! ($checklist[$key] ?? false)) {
                return false;
            }
        }

        return true;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function kantorPerwakilan(): BelongsTo
    {
        return $this->belongsTo(KantorPerwakilan::class, 'kantor_perwakilan_id');
    }

    public function currentOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'current_officer_id');
    }

    public function kendalaReason(): BelongsTo
    {
        return $this->belongsTo(KendalaReason::class);
    }

    public function fatPoint(): BelongsTo
    {
        return $this->belongsTo(FatPoint::class);
    }

    public function splitter(): BelongsTo
    {
        return $this->belongsTo(Splitter::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(UploadBatch::class, 'batch_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'pa_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(StatusLog::class, 'pa_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'pa_id');
    }

    public function bastDocument()
    {
        return $this->belongsTo(BastDocument::class, 'bast_document_id');
    }

    public function bastItems(): HasMany
    {
        return $this->hasMany(BastItem::class, 'pa_id');
    }

    public function paymentItems(): HasMany
    {
        return $this->hasMany(PaymentItem::class, 'pa_id');
    }

    public function qcRejectReason()
    {
        return $this->belongsTo(QcRejectReason::class, 'qc_reject_reason_id');
    }

    // Aging dalam hari: dari pa_date sampai hari ini, atau sampai completed_at bila sudah DONE
    public function getAgingAttribute(): int
    {
        $end = $this->completed_at ?? now();

        return (int) $this->pa_date->diffInDays($end);
    }

    // Perhitungan warna/prioritas aging (hijau/kuning/oranye/merah) sengaja
    // TIDAK ditaruh di sini — itu tanggung jawab App\Services\AgingCalculator
    // supaya ambang batasnya (dari tabel sla_settings) gampang diubah tanpa
    // menyentuh model. Model cukup menyediakan angka mentah lewat $aging.

    public function isOverSla(int $slaDays): bool
    {
        return $this->current_status !== self::STATUS_DONE && $this->aging > $slaDays;
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('current_status', $status);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('current_status', [self::STATUS_DONE]);
    }

    public function scopeInRegion($query, int $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    public function scopeAssignedTo($query, int $officerId)
    {
        return $query->where('current_officer_id', $officerId);
    }

    // PA yang sudah lewat sekian hari sejak pa_date dan belum DONE — dasar untuk FR-14/FR-15
    public function scopeOlderThan($query, int $days)
    {
        return $query->where('pa_date', '<=', now()->subDays($days))
            ->whereNotIn('current_status', [self::STATUS_DONE]);
    }
}
