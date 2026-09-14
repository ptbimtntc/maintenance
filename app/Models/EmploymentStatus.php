<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description', 'counts_as_active', 'is_active'])]
class EmploymentStatus extends Model
{
    /** @use HasFactory<\Database\Factories\EmploymentStatusFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'counts_as_active' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
