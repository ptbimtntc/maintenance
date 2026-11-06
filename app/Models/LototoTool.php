<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['board_id', 'code', 'name', 'location', 'image_path', 'is_active'])]
class LototoTool extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(LototoBoard::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LototoToolActivity::class, 'tool_id');
    }

    /** The tool is IN while it has an unfinished activity; the database allows at most one. */
    public function openActivity(): HasOne
    {
        return $this->hasOne(LototoToolActivity::class, 'tool_id')->where('status', 'in_progress');
    }
}
