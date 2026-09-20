<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['kabupaten_kota', 'kecamatan', 'kelurahan', 'parent_group'];

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
}
