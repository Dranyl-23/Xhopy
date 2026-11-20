<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $brand_id
 * @property int|null $seller_id
 * @property string $seller_name
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property string|null $short_description
 * @property string|null $description
 * @property ProductStatus $status
 * @property bool $is_featured
 * @property string $currency
 * @property string $base_price
 * @property string|null $compare_at_price
 * @property int $stock_quantity
 * @property array<string, string>|null $specifications
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductImage> $images
 */
#[Fillable([
    'category_id',
    'brand_id',
    'seller_id',
    'seller_name',
    'name',
    'slug',
    'sku',
    'short_description',
    'description',
    'status',
    'is_featured',
    'currency',
    'base_price',
    'compare_at_price',
    'stock_quantity',
    'specifications',
    'published_at',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'brand_id' => 'integer',
            'seller_id' => 'integer',
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'specifications' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('products.status', ProductStatus::Active->value)
            ->whereHas('category', fn (Builder $catQuery): Builder => $catQuery->where('is_active', true));
    }

    public function effectiveDefaultVariant(): ?ProductVariant
    {
        $activeVariants = $this->variants->where('is_active', true);

        if ($activeVariants->isEmpty()) {
            return null;
        }

        return $activeVariants->firstWhere('is_default', true)
            ?? $activeVariants->first();
    }

    public function resolvedUnitPrice(): string
    {
        $variant = $this->effectiveDefaultVariant();

        return $variant !== null ? (string) $variant->price : (string) $this->base_price;
    }

    public function resolvedCompareAtPrice(): ?string
    {
        $variant = $this->effectiveDefaultVariant();

        if ($variant !== null) {
            return $variant->compare_at_price !== null ? (string) $variant->compare_at_price : null;
        }

        return $this->compare_at_price !== null ? (string) $this->compare_at_price : null;
    }

    public function totalAvailableStock(): int
    {
        $activeVariants = $this->variants->where('is_active', true);

        if ($activeVariants->isNotEmpty()) {
            return (int) $activeVariants->sum('stock_quantity');
        }

        return max(0, (int) $this->stock_quantity);
    }
}
