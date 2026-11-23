<?php

namespace App\Domain\Catalog\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CatalogMediaService
{
    public const string DISK = 'local';

    /**
     * @var array<string, string>
     */
    private const array ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Store a validated catalog image privately and strip embedded metadata when GD is available.
     *
     * @return array{disk: string, path: string, mime_type: string, size_bytes: int}
     */
    public function storeProductImage(int $productId, UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            throw new InvalidArgumentException('Uploaded image file is unreadable.');
        }

        $rawBytes = file_get_contents($realPath);
        if ($rawBytes === false) {
            throw new InvalidArgumentException('Uploaded image file could not be read.');
        }

        $detectedMime = $this->detectMime($rawBytes);
        if (! isset(self::ALLOWED_MIME_EXTENSIONS[$detectedMime])) {
            throw new InvalidArgumentException('Unsupported image file content.');
        }

        $sanitizedBytes = $this->reencodeRasterBytes($rawBytes, $detectedMime);
        $extension = self::ALLOWED_MIME_EXTENSIONS[$detectedMime];
        $relativePath = sprintf('catalog/products/%d/%s.%s', $productId, (string) Str::uuid(), $extension);

        Storage::disk(self::DISK)->put($relativePath, $sanitizedBytes);

        return [
            'disk' => self::DISK,
            'path' => $relativePath,
            'mime_type' => $detectedMime,
            'size_bytes' => strlen($sanitizedBytes),
        ];
    }

    public function deleteStoredImage(string $disk, string $path): void
    {
        if ($path === '' || str_contains($path, '..')) {
            return;
        }

        $storage = Storage::disk($disk);
        if ($storage->exists($path)) {
            $storage->delete($path);
        }
    }

    private function detectMime(string $bytes): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        return (string) ($finfo->buffer($bytes) ?: '');
    }

    private function reencodeRasterBytes(string $rawBytes, string $mime): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $rawBytes;
        }

        $resource = @imagecreatefromstring($rawBytes);
        if ($resource === false) {
            throw new InvalidArgumentException('Uploaded image could not be decoded. Please upload a standard static JPG, PNG, or WebP image.');
        }

        ob_start();
        $encoded = match ($mime) {
            'image/jpeg' => imagejpeg($resource, null, 88),
            'image/png' => $this->encodePng($resource),
            'image/webp' => function_exists('imagewebp')
                ? $this->encodeWebp($resource)
                : $this->encodePng($resource),
            default => false,
        };
        $cleanBytes = ob_get_clean();

        if (! $encoded || $cleanBytes === false || $cleanBytes === '') {
            throw new InvalidArgumentException('Failed to re-encode uploaded image cleanly.');
        }

        return $cleanBytes;
    }

    private function encodePng(\GdImage $resource): bool
    {
        imagesavealpha($resource, true);

        return imagepng($resource, null, 6);
    }

    private function encodeWebp(\GdImage $resource): bool
    {
        imagesavealpha($resource, true);

        return imagewebp($resource, null, 86);
    }
}
