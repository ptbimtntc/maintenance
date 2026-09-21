<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['training_program_id', 'question_text', 'allow_multiple_answers'])]
class TrainingQuestion extends Model
{
    protected function casts(): array
    {
        return ['allow_multiple_answers' => 'boolean'];
    }

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(TrainingQuestionChoice::class)->orderBy('option_label');
    }
}
