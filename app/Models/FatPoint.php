<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FatPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_fat',
        'nama_fat',
        'region_id',
        'kantor_perwakilan_id',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function kantorPerwakilan(): BelongsTo
    {
        return $this->belongsTo(KantorPerwakilan::class, 'kantor_perwakilan_id');
    }

    public function splitters(): HasMany
    {
        return $this->hasMany(Splitter::class);
    }

    public function paOrders(): HasMany
    {
        return $this->hasMany(PaOrder::class, 'fat_point_id');
    }
}
