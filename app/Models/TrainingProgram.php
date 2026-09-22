<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title', 'code', 'training_category_id', 'training_type_id', 'description', 'objectives',
    'target_audience', 'is_internal', 'trainer_name', 'trainer_signature_path', 'training_provider_id', 'location_id',
    'duration_value', 'duration_unit', 'estimated_cost', 'budget_reference', 'status', 'remarks',
    'validity_months', 'passing_score', 'authorizer_name', 'authorizer_title', 'authorizer_signature_path', 'created_by',
    'trainer_signatory_id', 'authorizer_signatory_id',
])]
class TrainingProgram extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingProgramFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'inactive'];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'duration_value' => 'decimal:1',
            'estimated_cost' => 'decimal:2',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TrainingQuestion::class);
    }

    public function trainingCategory(): BelongsTo
    {
        return $this->belongsTo(TrainingCategory::class);
    }

    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(TrainingType::class);
    }

    public function trainingProvider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function trainerSignatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class, 'trainer_signatory_id');
    }

    public function authorizerSignatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class, 'authorizer_signatory_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'training_program_skill');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
