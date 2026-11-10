<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'employee_id',
    'spare_lock_id',
    'board',
    'location',
    'activity',
    'risks',
    'other_risk',
    'risk_controls',
    'knows_procedure',
    'follows_lototo',
    'uses_ppe',
    'safe_tools',
    'safe_hands',
    'supervisor_feedback',
    'supervisor_follow_up',
    'supervisor_follow_up_by',
    'supervisor_follow_up_at',
    'supervisor_follow_up_status',
    'status',
    'outcome',
    'out_details',
    'started_at',
    'ended_at',
])]
class LototoActivity extends Model
{
    /** Activity timestamps are stored in UTC; LOTOTO pages show them in the plant's local time. */
    public const DISPLAY_TIMEZONE = 'Asia/Jakarta';

    public static function localTime(?CarbonInterface $time): ?CarbonInterface
    {
        return $time?->copy()->setTimezone(self::DISPLAY_TIMEZONE);
    }

    public static function localTimeFromUtc(?CarbonInterface $time): ?CarbonInterface
    {
        return $time
            ? Carbon::parse($time->format('Y-m-d H:i:s.u'), 'UTC')->setTimezone(self::DISPLAY_TIMEZONE)
            : null;
    }

    protected function casts(): array
    {
        return [
            'risks' => 'array',
            'out_details' => 'array',
            'knows_procedure' => 'boolean',
            'follows_lototo' => 'boolean',
            'uses_ppe' => 'boolean',
            'safe_tools' => 'boolean',
            'safe_hands' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'supervisor_follow_up_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function spareLock(): BelongsTo
    {
        return $this->belongsTo(SpareLock::class);
    }

    public function supervisorFollowUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_follow_up_by');
    }
}
