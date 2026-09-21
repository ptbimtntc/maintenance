<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_question_id', 'option_label', 'choice_text', 'is_correct', 'points'])]
class TrainingQuestionChoice extends Model
{
    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuestion::class, 'training_question_id');
    }
}
