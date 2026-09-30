<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeEntry extends Model
{
    use HasFactory;

    public const COMPENSATION_TYPES = ['OT_PAID', 'OT_LEAVE'];

    public const STATUS_LOCKED = 'locked';

    public const STATUS_EDIT_REQUESTED = 'edit_requested';

    public const STATUS_EDIT_APPROVED = 'edit_approved';

    public const STATUSES = [self::STATUS_LOCKED, self::STATUS_EDIT_REQUESTED, self::STATUS_EDIT_APPROVED];

    protected $fillable = [
        'employee_id',
        'created_by',
        'start_at',
        'end_at',
        'remarks',
        'compensation_type',
        'status',
        'edit_request_reason',
        'edit_requested_at',
        'edit_approved_by',
        'edit_approved_at',
        'submitted_to_hr_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'edit_requested_at' => 'datetime',
            'edit_approved_at' => 'datetime',
            'submitted_to_hr_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_approved_by');
    }

    /** Never stored - always derived from start/end so it can't drift if either changes. */
    protected function durationHours(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_at && $this->end_at
                ? round($this->start_at->diffInMinutes($this->end_at) / 60, 2)
                : 0.0,
        );
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function hasPendingEditRequest(): bool
    {
        return $this->status === self::STATUS_EDIT_REQUESTED;
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_EDIT_APPROVED;
    }

    public function scopeNotSubmitted($query)
    {
        return $query->whereNull('submitted_to_hr_at');
    }

    public static function compensationLabel(string $type): string
    {
        return match ($type) {
            'OT_PAID' => 'Paid Overtime',
            'OT_LEAVE' => 'Replacement Leave',
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_LOCKED => 'Locked',
            self::STATUS_EDIT_REQUESTED => 'Edit Requested',
            self::STATUS_EDIT_APPROVED => 'Edit Approved',
            default => $status,
        };
    }
}
