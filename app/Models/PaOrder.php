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
        'pa_number', 'customer_id', 'customer_name', 'contact_phone', 'address',
        'region_id', 'pa_date', 'current_status', 'current_officer_id',
        'assigned_date', 'started_at', 'completed_at', 'kendala_reason_id',
        'notes', 'batch_id',
    ];

    protected $casts = [
        'pa_date' => 'date',
        'assigned_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Nilai current_status mengikuti enum di migration
    public const STATUS_UNASSIGNED = 'UNASSIGNED';
    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_ON_PROGRESS = 'ON_PROGRESS';
    public const STATUS_DONE = 'DONE';
    public const STATUS_KENDALA = 'KENDALA';

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function currentOfficer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'current_officer_id');
    }

    public function kendalaReason(): BelongsTo
    {
        return $this->belongsTo(KendalaReason::class);
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
