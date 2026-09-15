<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'position_id', 'reports_to_position_id', 'job_title', 'job_purpose', 'main_responsibilities',
    'detailed_duties', 'required_education', 'required_experience', 'required_technical_skills',
    'required_soft_skills', 'required_certifications', 'safety_responsibilities', 'direct_reports_summary',
    'version', 'effective_date', 'review_date', 'status', 'approved_by', 'approval_date',
    'remarks', 'created_by', 'updated_by',
])]
class JobDescription extends Model
{
    /** @use HasFactory<\Database\Factories\JobDescriptionFactory> */
    use HasFactory, SoftDeletes, Auditable;

    public const STATUSES = ['draft', 'pending_review', 'active', 'archived'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'review_date' => 'date',
            'approval_date' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function reportsToPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
