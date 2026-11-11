<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LototoActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The link in a LOTOTO follow-up email: it opens the Supervisor Feedback page of one activity for one supervisor,
 * without a login. Its signature covers the activity, the supervisor and the expiry (path and query only, so the
 * link keeps working behind another host or scheme, like the LOTOTO QR codes). The page answers only when the
 * signature is valid and not expired, the supervisor is still the technician's supervisor, and a signed-in user
 * (if any) is that supervisor; otherwise it shows nothing of the activity.
 */
class LototoFollowUpLink
{
    public const VALID_DAYS = 30;

    public static function url(LototoActivity $activity, Employee $supervisor): string
    {
        return url(URL::temporarySignedRoute('safety.lototo.follow-up.respond', now()->addDays(self::VALID_DAYS), [
            'activity' => $activity->id,
            'supervisor' => $supervisor->id,
        ], absolute: false));
    }

    /** The supervisor this request may act as, or null when the link must not open. */
    public static function supervisorFor(Request $request, LototoActivity $activity): ?Employee
    {
        if (! $request->hasValidSignature(false)) {
            return null;
        }
        $supervisor = Employee::query()->find((int) $request->query('supervisor'));
        if (! $supervisor || (int) $activity->employee?->supervisor_id !== (int) $supervisor->id) {
            return null;
        }
        $user = $request->user();
        if ($user && ! $user->hasRole(RoleName::Guest->value) && (int) $user->employee?->id !== (int) $supervisor->id) {
            return null;
        }

        return $supervisor;
    }
}
