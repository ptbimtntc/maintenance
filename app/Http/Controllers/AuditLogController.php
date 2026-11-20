<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    use \App\Concerns\FiltersByMultipleIds;

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

        $types = $this->valueList($request->input('auditable_type'));
        $actions = $this->valueList($request->input('action'));

        $logs = AuditLog::query()
            ->with('user')
            ->when($types !== [], fn ($q) => $q->whereIn('auditable_type', $types))
            ->when($actions !== [], fn ($q) => $q->whereIn('action', $actions))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'auditableTypes' => $auditableTypes,
            'filters' => ['auditable_type' => $types, 'action' => $actions],
        ]);
    }
}
