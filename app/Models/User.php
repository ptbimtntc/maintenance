<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MenuKey;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function menuPermissions(): HasMany
    {
        return $this->hasMany(UserMenuPermission::class);
    }

    /**
     * Whether this user may create/edit/delete within the given menu.
     * Every menu defaults to read-only (see MenuKey::editableByDefault())
     * unless an administrator has explicitly granted edit rights via the
     * User Management screen. Guest is hard-blocked here regardless of any
     * override that might exist, as a second layer of defense on top of
     * the Guest role simply never being granted Manage* permissions.
     */
    public function canEditMenu(MenuKey|string $menu): bool
    {
        if ($this->hasRole(RoleName::Guest->value)) {
            return false;
        }

        // Administrators configure this permission system for everyone
        // else - it would be self-defeating for it to ever lock them out.
        if ($this->hasRole(RoleName::Administrator->value)) {
            return true;
        }

        $menu = $menu instanceof MenuKey ? $menu : MenuKey::from($menu);

        $override = $this->relationLoaded('menuPermissions')
            ? $this->menuPermissions->firstWhere('menu_key', $menu->value)
            : $this->menuPermissions()->where('menu_key', $menu->value)->first();

        if ($override) {
            return (bool) $override->can_edit;
        }

        return $menu->editableByDefault() || $this->hasAnyPermission($menu->managePermissionValues());
    }
}
