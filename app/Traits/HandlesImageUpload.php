<?php

namespace App\Traits;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

trait HandlesImageUpload
{
    /**
     * Public URL for a path stored on a disk that supports URLs (e.g. public).
     */
    protected function publicStorageUrl(string $relativePath, string $diskName = 'public'): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        return $disk->url($relativePath);
    }

    /**
     * Resize (cover), encode as JPEG, store on the given disk. Returns the storage-relative path.
     */
    public function uploadImage(UploadedFile $file, string $directory, ?int $width = 800, ?int $height = 600, string $disk = 'public'): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->decodeSplFileInfo($file);

        if ($width && $height) {
            $image->cover($width, $height);
        } elseif ($width) {
            $image->scale(width: $width);
        } elseif ($height) {
            $image->scale(height: $height);
        }

        $encoded = $image->encode(new JpegEncoder(quality: 85));
        $name = Str::uuid().'.jpg';
        $path = trim($directory, '/').'/'.$name;

        Storage::disk($disk)->put($path, (string) $encoded);

        return $path;
    }

    /**
     * Square center-cropped avatar, max 400px.
     */
    public function uploadAvatar(UploadedFile $file, string $disk = 'public'): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->decodeSplFileInfo($file);
        $size = min($image->width(), $image->height());
        $image->cover($size, $size);
        $image->scale(width: 400, height: 400);
        $encoded = $image->encode(new JpegEncoder(quality: 88));
        $name = 'avatars/'.Str::uuid().'.jpg';
        Storage::disk($disk)->put($name, (string) $encoded);

        return $name;
    }

    /**
     * Main image plus smaller thumbnail.
     *
     * @return array{0: string, 1: string} [imagePath, thumbPath]
     */
    public function uploadPortfolioImages(UploadedFile $file, string $disk = 'public'): array
    {
        try {
            $main = $this->uploadImage($file, 'portfolio', 800, 600, $disk);
            $manager = new ImageManager(new Driver);
            $thumb = $manager->decodeSplFileInfo($file);
            $thumb->cover(400, 300);
            $thumbName = 'portfolio/thumb_'.Str::uuid().'.jpg';
            Storage::disk($disk)->put($thumbName, $thumb->encode(new JpegEncoder(quality: 80))->toString());

            return [$main, $thumbName];
        } catch (Throwable $e) {
            Log::error('Portfolio image processing failed.', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
