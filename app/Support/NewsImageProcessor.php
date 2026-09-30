<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Downscales and re-compresses a news image before it lands in storage, so
 * an admin dropping in a straight-off-the-phone 8MB photo doesn't ship that
 * full size to every dashboard visitor. Requires the GD or Imagick PHP
 * extension; if neither is available (e.g. a bare-bones local dev box),
 * this silently falls back to storing the original upload untouched rather
 * than failing the whole request - resizing is an optimization, not a
 * hard requirement for the news feature to work.
 */
class NewsImageProcessor
{
    private const MAX_WIDTH = 1600;

    private const JPEG_QUALITY = 80;

    public static function store(UploadedFile $file, string $directory): string
    {
        $manager = self::manager();

        if (! $manager) {
            return $file->store($directory, 'public');
        }

        try {
            $image = $manager->read($file->getRealPath());
            $image->scaleDown(width: self::MAX_WIDTH);

            $path = $directory.'/'.(string) Str::uuid().'.jpg';
            Storage::disk('public')->put($path, (string) $image->toJpeg(quality: self::JPEG_QUALITY));

            return $path;
        } catch (Throwable $e) {
            Log::warning('News image processing failed, storing original upload instead.', ['error' => $e->getMessage()]);

            return $file->store($directory, 'public');
        }
    }

    private static function manager(): ?ImageManager
    {
        if (extension_loaded('gd')) {
            return new ImageManager(new GdDriver());
        }

        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver());
        }

        return null;
    }
}
