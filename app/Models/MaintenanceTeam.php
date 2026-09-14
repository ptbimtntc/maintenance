<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['maintenance_area_id', 'name', 'code', 'description', 'is_active'])]
class MaintenanceTeam extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceTeamFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function maintenanceArea(): BelongsTo
    {
        return $this->belongsTo(MaintenanceArea::class);
    }
}
