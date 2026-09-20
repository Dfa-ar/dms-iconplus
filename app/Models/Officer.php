<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Officer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'employee_code', 'name', 'phone',
        'region_id', 'is_active', 'daily_target',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function currentPaOrders(): HasMany
    {
        return $this->hasMany(PaOrder::class, 'current_officer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInRegion($query, int $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    // Berapa PA yang ditugaskan ke petugas ini pada tanggal tertentu (default: hari ini)
    // — dipakai Auto-Assignment untuk cek sisa kuota sebelum menambah tugas baru.
    public function assignedCountOn(?\Carbon\Carbon $date = null): int
    {
        $date ??= now();

        return $this->assignments()->whereDate('assign_date', $date)->count();
    }

    public function hasQuotaLeftOn(?\Carbon\Carbon $date = null): bool
    {
        return $this->assignedCountOn($date) < $this->daily_target;
    }
}
