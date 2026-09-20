<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaSetting extends Model
{
    protected $fillable = [
        'sla_days', 'aging_green_max', 'aging_yellow_max', 'aging_orange_max',
    ];

    // Tabel ini didesain sebagai satu baris konfigurasi (singleton).
    // Dipakai AgingCalculator supaya tidak perlu query manual di banyak tempat.
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'sla_days' => 14,
            'aging_green_max' => 2,
            'aging_yellow_max' => 6,
            'aging_orange_max' => 13,
        ]);
    }
}
