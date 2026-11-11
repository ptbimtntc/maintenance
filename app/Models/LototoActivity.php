<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** Every supervisor reply to this activity's follow-up, oldest first (history; none is overwritten). */
    public function supervisorFeedbacks(): HasMany
    {
        return $this->hasMany(LototoSupervisorFeedback::class, 'lototo_activity_id')->orderBy('created_at')->orderBy('id');
    }

    /** Relations the LOTOTO Activity pages load for the feedback column (reply history only once its table exists). */
    public static function feedbackRelations(): array
    {
        return \Illuminate\Support\Facades\Schema::hasTable('lototo_supervisor_feedbacks')
            ? ['supervisorFollowUpBy', 'supervisorFeedbacks.supervisor', 'supervisorFeedbacks.user']
            : ['supervisorFollowUpBy'];
    }

    /** The five checklist answers as [field => label], in the order of the form and the email. */
    public const CHECKLIST = [
        'knows_procedure' => 'Saya tahu bagaimana melakukan pekerjaan ini',
        'follows_lototo' => 'Saya melakukan standar LOTOTO',
        'uses_ppe' => 'Saya menggunakan APD dengan baik dan benar',
        'safe_tools' => 'Saya memiliki tools dengan kondisi aman untuk digunakan',
        'safe_hands' => 'Jari / tangan saya AMAN (tidak dalam LoF)',
    ];

    /**
     * The supervisor replies to show: the history rows, or for a reply saved before the history table existed (or
     * before its migration ran), the reply stored on the activity itself.
     *
     * @return \Illuminate\Support\Collection<int, array{comment: string, by: string, at: ?CarbonInterface}>
     */
    public function supervisorReplies(): \Illuminate\Support\Collection
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('lototo_supervisor_feedbacks')) {
            $replies = $this->supervisorFeedbacks->map(fn (LototoSupervisorFeedback $reply) => [
                'comment' => $reply->comment,
                'by' => $reply->authorName(),
                'at' => self::localTimeFromUtc($reply->created_at),
            ]);
            if ($replies->isNotEmpty()) {
                return $replies;
            }
        }

        return filled($this->supervisor_follow_up)
            ? collect([['comment' => $this->supervisor_follow_up, 'by' => $this->supervisorFollowUpBy?->name ?? 'Supervisor', 'at' => self::localTimeFromUtc($this->supervisor_follow_up_at)]])
            : collect();
    }
}
