<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable name/title/signature-image an admin can pick as the Trainer or
 * Authorizer on a Training Program (see TrainingProgramController), instead
 * of re-typing the name and re-uploading the same signature image on every
 * program.
 */
#[Fillable(['name', 'title', 'signature_path', 'is_active'])]
class Signatory extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
