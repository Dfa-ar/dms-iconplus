<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'evidences';

    protected $fillable = [
        'pa_id', 'type', 'file_path', 'uploaded_by',
        'uploaded_at', 'receiver_name', 'pickup_time',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'pickup_time' => 'datetime',
    ];

    public function paOrder(): BelongsTo
    {
        return $this->belongsTo(PaOrder::class, 'pa_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
