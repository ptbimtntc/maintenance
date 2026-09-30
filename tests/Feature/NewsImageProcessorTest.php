<?php

namespace Tests\Feature;

use App\Support\NewsImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsImageProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $this->markTestSkipped('Neither GD nor Imagick is available in this environment - NewsImageProcessor falls back to storing the original upload untouched, which is covered by NewsTest instead.');
        }
    }

    public function test_it_downscales_a_wide_image_and_stores_it_as_a_compressed_jpeg(): void
    {
        Storage::fake('public');

        $wide = imagecreatetruecolor(3000, 1500);
        imagefill($wide, 0, 0, imagecolorallocate($wide, 255, 0, 0));
        $tmpPath = tempnam(sys_get_temp_dir(), 'news').'.jpg';
        imagejpeg($wide, $tmpPath, 100);
        imagedestroy($wide);

        $file = new UploadedFile($tmpPath, 'wide.jpg', 'image/jpeg', null, true);
        $originalSize = filesize($tmpPath);

        $path = NewsImageProcessor::store($file, 'news-images');

        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.jpg', $path);

        $storedSize = Storage::disk('public')->size($path);
        $this->assertLessThan($originalSize, $storedSize, 'Resized+recompressed image should be smaller than the uncompressed 3000x1500 original.');

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(1600, $width);

        @unlink($tmpPath);
    }

    public function test_it_does_not_upscale_a_small_image(): void
    {
        Storage::fake('public');

        $small = imagecreatetruecolor(200, 100);
        imagefill($small, 0, 0, imagecolorallocate($small, 0, 255, 0));
        $tmpPath = tempnam(sys_get_temp_dir(), 'news').'.jpg';
        imagejpeg($small, $tmpPath, 100);
        imagedestroy($small);

        $file = new UploadedFile($tmpPath, 'small.jpg', 'image/jpeg', null, true);

        $path = NewsImageProcessor::store($file, 'news-images');

        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(200, $width);

        @unlink($tmpPath);
    }
}
