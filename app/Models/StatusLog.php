<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'pa_id', 'from_status', 'to_status', 'changed_by',
        'kendala_reason_id', 'note', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function paOrder(): BelongsTo
    {
        return $this->belongsTo(PaOrder::class, 'pa_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function kendalaReason(): BelongsTo
    {
        return $this->belongsTo(KendalaReason::class);
    }
}
