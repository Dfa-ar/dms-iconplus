<?php

namespace App\Observers;

use App\Models\SlaSetting;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MasterDataAuditObserver
{
    public function created(Model $model): void
    {
        if (! Auth::check() || $model instanceof SlaSetting) {
            return;
        }

        AuditLogger::log(
            'master_created',
            class_basename($model),
            $model->getKey(),
            'Data master dibuat. Field: ' . implode(', ', array_keys($model->getAttributes()))
        );
    }

    public function updated(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $fields = array_values(array_diff(array_keys($model->getChanges()), ['updated_at']));
        if ($fields === []) {
            return;
        }

        AuditLogger::log(
            'master_updated',
            class_basename($model),
            $model->getKey(),
            'Data master diperbarui. Field: ' . implode(', ', $fields)
        );
    }

    public function deleted(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        AuditLogger::log(
            'master_deleted',
            class_basename($model),
            $model->getKey(),
            'Data master dihapus.'
        );
    }
}