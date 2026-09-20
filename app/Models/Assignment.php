<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pa_id', 'officer_id', 'assign_date', 'assigned_by', 'source', 'released_at',
    ];

    protected $casts = [
        'assign_date' => 'date',
        'released_at' => 'datetime',
    ];

    public function paOrder(): BelongsTo
    {
        return $this->belongsTo(PaOrder::class, 'pa_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
