<?php

namespace App\Domain\Seller\Services;

use App\Domain\Seller\Enums\SellerDocumentType;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerVerificationDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SellerDocumentStorageService
{
    public const string DISK = 'local';

    public const int MAX_BYTES = 5_242_880; // 5 MiB

    /**
     * @var array<string, string>
     */
    private const array MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function store(
        SellerProfile $profile,
        SellerDocumentType $type,
        UploadedFile $file,
    ): SellerVerificationDocument {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded verification file is not valid.');
        }

        $sizeBytes = $file->getSize();
        if (! is_int($sizeBytes) || $sizeBytes <= 0 || $sizeBytes > self::MAX_BYTES) {
            throw new InvalidArgumentException('Verification document must be between 1 byte and 5 MB.');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            throw new InvalidArgumentException('Uploaded file cannot be read for inspection.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo !== false ? finfo_file($finfo, $realPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (! is_string($detectedMime) || ! array_key_exists($detectedMime, self::MIME_TO_EXTENSION)) {
            throw new InvalidArgumentException('Unsupported file type. Upload a PDF, JPEG, PNG, or WebP document.');
        }

        $extension = self::MIME_TO_EXTENSION[$detectedMime];
        $directory = "seller-verification/{$profile->id}";
        $filename = Str::uuid()->toString().'.'.$extension;
        $storedPath = Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        if (! is_string($storedPath) || $storedPath === '') {
            throw new InvalidArgumentException('Unable to store verification document securely.');
        }

        return SellerVerificationDocument::query()->create([
            'seller_profile_id' => $profile->id,
            'document_type' => $type,
            'original_filename' => self::sanitizeOriginalFilename($file->getClientOriginalName(), $extension),
            'disk' => self::DISK,
            'path' => $storedPath,
            'mime_type' => $detectedMime,
            'size_bytes' => $sizeBytes,
        ]);
    }

    public function download(SellerVerificationDocument $document): StreamedResponse
    {
        if (! $this->isValidDocumentPath($document)) {
            abort(404);
        }

        if (! Storage::disk($document->disk)->exists($document->path)) {
            abort(404);
        }

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function delete(SellerVerificationDocument $document): void
    {
        if ($this->isValidDocumentPath($document) && Storage::disk($document->disk)->exists($document->path)) {
            Storage::disk($document->disk)->delete($document->path);
        }

        $document->delete();
    }

    private function isValidDocumentPath(SellerVerificationDocument $document): bool
    {
        $normalized = str_replace('\\', '/', $document->path);
        $expectedPrefix = "seller-verification/{$document->seller_profile_id}/";

        return $document->disk === self::DISK
            && str_starts_with($normalized, $expectedPrefix)
            && ! str_contains($normalized, '..');
    }

    private static function sanitizeOriginalFilename(string $rawName, string $fallbackExtension): string
    {
        $base = basename(str_replace('\\', '/', $rawName));
        $cleaned = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
        $cleaned = trim($cleaned, '-.');

        if ($cleaned === '') {
            return 'verification-document.'.$fallbackExtension;
        }

        return mb_substr($cleaned, 0, 180);
    }
}
