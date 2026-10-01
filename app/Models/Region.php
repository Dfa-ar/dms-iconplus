<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = [
        'kabupaten_kota',
        'kecamatan',
        'kelurahan',
        'parent_group',
        'kantor_perwakilan_id',
        'office_code',
        'office_name',
        'level',
        'parent_region_id',
        'is_office',
        'lat',
        'lng',
    ];

    protected $casts = [
        'is_office' => 'boolean',
    ];

    public function kantorPerwakilan(): BelongsTo
    {
        return $this->belongsTo(KantorPerwakilan::class, 'kantor_perwakilan_id');
    }

    public function parentOffice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_region_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_region_id');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(Officer::class);
    }

    public function paOrders(): HasMany
    {
        return $this->hasMany(PaOrder::class);
    }

    public function scopeInGroup($query, string $parentGroup)
    {
        return $query->where('parent_group', $parentGroup);
    }

    public function scopeOffice($query)
    {
        return $query->where('is_office', true);
    }

    public function scopeAreas($query)
    {
        return $query->where('is_office', false);
    }
}
