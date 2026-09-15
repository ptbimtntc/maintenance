<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A per-user override of the default edit/read-only state for one menu
 * (see App\Enums\MenuKey). Absence of a row means "use the menu's default"
 * - see User::canEditMenu().
 */
#[Fillable(['user_id', 'menu_key', 'can_edit'])]
class UserMenuPermission extends Model
{
    protected function casts(): array
    {
        return [
            'can_edit' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
