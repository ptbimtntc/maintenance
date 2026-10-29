<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['board_id', 'category', 'position', 'employee_id', 'spare_lock_id', 'tool_label', 'tool_id', 'tool_column_span', 'is_enabled'])]
class LototoBoardSlot extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(LototoBoard::class, 'board_id', 'id');
    }

    public function spareLock(): BelongsTo
    {
        return $this->belongsTo(SpareLock::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(LototoTool::class, 'tool_id');
    }
}
