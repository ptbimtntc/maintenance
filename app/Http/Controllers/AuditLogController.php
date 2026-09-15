<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * A read-only audit trail for administrators: who created, changed, or
     * deleted which record, and when. Restricted to the same permission
     * that gates user/system administration.
     */
    public function index(Request $request): View
    {
        $auditableTypes = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->pluck('auditable_type');

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->string('auditable_type')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'auditableTypes' => $auditableTypes,
            'filters' => $request->only(['auditable_type', 'action']),
        ]);
    }
}
