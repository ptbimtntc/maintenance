<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A simple key-value application settings store, per the project brief's
 * database design. Values are cached (cleared on write) so reading a
 * setting doesn't cost a query on every request.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'app-settings';

    /**
     * Known settings and their defaults. Centralised here rather than
     * scattered as magic numbers throughout the app.
     */
    public const DEFAULTS = [
        'certificate_expiring_soon_days' => '60',
        'training_reminder_days_before' => '7',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allCached()[$key] ?? null;

        return $value ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function getInt(string $key, int $default): int
    {
        return (int) static::get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cached as a plain array (not a Collection) since some cache stores
     * have been observed to return a stale/incomplete object for anything
     * more complex than array/scalar values when unserializing.
     *
     * @return array<string, mixed>
     */
    private static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::all()->pluck('value', 'key')->all());
    }
}
