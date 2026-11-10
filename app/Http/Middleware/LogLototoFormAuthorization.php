<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\SpareLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogLototoFormAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $employee = $user?->employee;
        $spareCode = $request->query('spare_lock');
        $boardKey = $request->query('board');
        $spareLock = is_string($spareCode)
            ? SpareLock::query()
                ->where('code', $spareCode)
                ->whereHas('board', fn ($boards) => is_string($boardKey) && $boardKey !== ''
                    ? $boards->where(fn ($board) => $board->where('id', $boardKey)->orWhere('code', $boardKey))
                    : $boards->where('code', 'mtn_tr'))
                ->with('currentActivity.employee')
                ->first()
            : null;
        $activityEmployee = $spareLock?->currentActivity?->employee;

        $context = [
            'user_id' => $user?->getAuthIdentifier(),
            'roles' => $user?->getRoleNames()->all() ?? [],
            'safety_view' => $user?->can(PermissionName::ViewSafety->value) ?? false,
            'safety_manage' => $user?->can(PermissionName::ManageSafety->value) ?? false,
            'has_employee' => $employee !== null,
            'own_lototo_number_matches_query' => $employee !== null && (string) $employee->lototo_number === (string) $request->query('lototo_number'),
            'spare_code' => $spareCode,
            'spare_found' => $spareLock !== null,
            'spare_has_active_activity' => $activityEmployee !== null,
            'spare_activity_belongs_to_user' => $activityEmployee !== null && $employee?->is($activityEmployee),
        ];

        Log::info('LOTOTO form authorization diagnostic: request', $context);

        try {
            $response = $next($request);
            Log::info('LOTOTO form authorization diagnostic: response', $context + ['status' => $response->getStatusCode()]);

            return $response;
        } catch (Throwable $exception) {
            Log::warning('LOTOTO form authorization diagnostic: exception', $context + [
                'exception' => get_class($exception),
                'status' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : null,
            ]);

            throw $exception;
        }
    }
}
