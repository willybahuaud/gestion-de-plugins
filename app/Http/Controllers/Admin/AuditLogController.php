<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::with('admin')
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', '%' . class_basename($request->model_type) . '%');
        }

        $logs = $query->paginate(50)->withQueryString();

        $actions = AuditLog::select('action')
            ->distinct()
            ->pluck('action');

        return view('admin.audit-logs.index', compact('logs', 'actions'));
    }
}
