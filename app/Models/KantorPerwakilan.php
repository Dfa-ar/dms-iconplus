<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KantorPerwakilan extends Model
{
    use HasFactory;

    protected $table = 'kantor_perwakilan';

    protected $fillable = [
        'nama',
        'kode',
        'alamat',
        'latitude',
        'longitude',
        'pic_nama',
        'pic_unit',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class, 'kantor_perwakilan_id');
    }

    public function officers(): HasMany
    {
        return $this->hasMany(Officer::class, 'kantor_perwakilan_id');
    }

    public function bastDocuments(): HasMany
    {
        return $this->hasMany(BastDocument::class, 'kantor_perwakilan_id');
    }
}
