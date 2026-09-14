<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['level_number', 'name', 'description', 'color', 'is_active'])]
class CompetencyLevel extends Model
{
    /** @use HasFactory<\Database\Factories\CompetencyLevelFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'level_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
