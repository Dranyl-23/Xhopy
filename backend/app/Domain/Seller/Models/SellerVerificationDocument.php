<?php

namespace App\Domain\Seller\Models;

use App\Domain\Seller\Enums\SellerDocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $seller_profile_id
 * @property SellerDocumentType $document_type
 * @property string $original_filename
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SellerProfile $sellerProfile
 */
#[Fillable([
    'seller_profile_id',
    'document_type',
    'original_filename',
    'disk',
    'path',
    'mime_type',
    'size_bytes',
])]
class SellerVerificationDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_profile_id' => 'integer',
            'document_type' => SellerDocumentType::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SellerProfile, $this>
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }
}
