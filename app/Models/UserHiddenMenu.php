<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sidebar menu (see App\Enums\SidebarMenu) hidden from one user.
 * Absence of a row means the menu is visible - see User::isMenuHidden().
 */
#[Fillable(['user_id', 'menu_key'])]
class UserHiddenMenu extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
