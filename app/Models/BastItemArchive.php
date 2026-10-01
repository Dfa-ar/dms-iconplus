<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BastItemArchive extends Model
{
    protected $table = 'bast_item_archives';

    protected $fillable = [
        'original_item_id',
        'bast_document_id',
        'pa_id',
        'serial_number',
        'location',
        'archive_reason',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
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