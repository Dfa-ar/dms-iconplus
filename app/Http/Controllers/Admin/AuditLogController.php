<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manageSettings');

        $filters = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:120'],
            'entity' => ['nullable', 'string', 'max:120'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', '%' . $action . '%'))
            ->when($filters['entity'] ?? null, fn ($query, $entity) => $query->where('entity', 'like', '%' . $entity . '%'))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.audit.index', compact('logs', 'filters', 'users'));
    }
}