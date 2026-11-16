<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Facades\Storage;

/**
 * An employee's photo on the LOTOTO pages. The board used to point every <img> at {APP_URL}/storage/<path>, which only
 * works when the public/storage link exists, APP_URL is the address the page is opened from, and the file is there;
 * otherwise each card showed a broken image. The photo is now streamed by the application itself (route
 * safety.lototo.employee-photo, same origin, no storage link needed), and the image is only used when the file
 * really exists: without one the card shows the employee's initials.
 */
class LototoEmployeePhoto
{
    /** @var array<int, string|null> */
    private static array $resolved = [];

    /**
     * The path on the public disk of this employee's photo, or null when there is none or the file is missing. Photos
     * are saved as employee-photos/<name> on the public disk; paths saved with a "public/" or "storage/" prefix, a
     * leading slash, or only the file name are found too.
     */
    public static function path(Employee $employee): ?string
    {
        if (! filled($employee->photo_path)) {
            return null;
        }

        return self::$resolved[$employee->id.'|'.$employee->photo_path] ??= self::find((string) $employee->photo_path);
    }

    /** The URL the page uses for the photo (relative, so it follows the address the page was opened from), or null. */
    public static function url(Employee $employee): ?string
    {
        if (self::path($employee) === null) {
            return null;
        }

        return route('safety.lototo.employee-photo', ['employee' => $employee->id, 'v' => $employee->updated_at?->timestamp], absolute: false);
    }

    /** Forgets what was looked up (tests that change files within one process). */
    public static function forget(): void
    {
        self::$resolved = [];
    }

    private static function find(string $stored): ?string
    {
        $disk = Storage::disk('public');
        $path = ltrim(str_replace('\\', '/', trim($stored)), '/');
        foreach (array_unique([
            $path,
            preg_replace('#^(public|storage|app/public)/#', '', $path),
            'employee-photos/'.basename($path),
        ]) as $candidate) {
            if (filled($candidate) && ! str_contains($candidate, '..') && $disk->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
