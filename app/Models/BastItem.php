<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BastItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bast_document_id',
        'pa_id',
        'serial_number',
        'location',
    ];

    public function bastDocument(): BelongsTo
    {
        return $this->belongsTo(BastDocument::class);
    }

    public function paOrder(): BelongsTo
    {
        return $this->belongsTo(PaOrder::class, 'pa_id');
    }
}
