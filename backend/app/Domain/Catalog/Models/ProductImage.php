<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\Catalog\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $variant_id
 * @property string $disk
 * @property string $path
 * @property string $alt_text
 * @property int $sort_order
 * @property bool $is_primary
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 */
#[Fillable([
    'product_id',
    'variant_id',
    'disk',
    'path',
    'alt_text',
    'sort_order',
    'is_primary',
    'mime_type',
    'size_bytes',
])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    protected static function newFactory(): ProductImageFactory
    {
        return ProductImageFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
