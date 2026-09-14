<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['department_id', 'name', 'code', 'description', 'is_active'])]
class MaintenanceArea extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceAreaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(MaintenanceTeam::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
