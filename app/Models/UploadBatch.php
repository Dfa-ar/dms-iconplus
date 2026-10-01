<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UploadBatch extends Model
{
    protected $fillable = [
        'file_name', 'uploaded_by', 'uploaded_at',
        'total_rows', 'success_rows', 'failed_rows', 'error_report_path', 'status',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function paOrders(): HasMany
    {
        return $this->hasMany(PaOrder::class, 'batch_id');
    }
}
