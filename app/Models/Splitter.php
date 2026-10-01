<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Splitter extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_splitter',
        'nama_splitter',
        'fat_point_id',
        'capacity_port',
        'total_port',
        'is_active',
    ];

    protected $casts = [
        'capacity_port' => 'integer',
        'total_port' => 'integer',
        'is_active' => 'boolean',
    ];

    public function fatPoint(): BelongsTo
    {
        return $this->belongsTo(FatPoint::class);
    }

    public function getCapacityPortAttribute($value): int
    {
        return (int) ($value ?? $this->total_port ?? 24);
    }

    public function setCapacityPortAttribute($value): void
    {
        $this->attributes['capacity_port'] = $value;
        if (! array_key_exists('total_port', $this->attributes) || $this->attributes['total_port'] === null) {
            $this->attributes['total_port'] = $value;
        }
    }

    public function paOrders(): HasMany
    {
        return $this->hasMany(PaOrder::class, 'splitter_id');
    }
}
