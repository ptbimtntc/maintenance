<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_id', 'code', 'is_active', 'current_activity_id'])]
class SpareLock extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function currentActivity(): BelongsTo
    {
        return $this->belongsTo(LototoActivity::class, 'current_activity_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(LototoBoard::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LototoActivity::class);
    }
}
