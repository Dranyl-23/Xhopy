<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\Store;
use App\Models\User;
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
 * @property int|null $store_id
 * @property string $seller_name
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property string|null $short_description
 * @property string|null $description
 * @property ProductStatus $status
 * @property string|null $moderation_notes
 * @property bool $is_featured
 * @property string $currency
 * @property string $base_price
 * @property string|null $compare_at_price
 * @property int $stock_quantity
 * @property array<string, string>|null $specifications
 * @property Carbon|null $published_at
 * @property Carbon|null $submitted_for_review_at
 * @property Carbon|null $moderated_at
 * @property int|null $moderated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Store|null $store
 * @property-read User|null $moderator
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductImage> $images
 */
#[Fillable([
    'category_id',
    'brand_id',
    'seller_id',
    'store_id',
    'seller_name',
    'name',
    'slug',
    'sku',
    'short_description',
    'description',
    'status',
    'moderation_notes',
    'is_featured',
    'currency',
    'base_price',
    'compare_at_price',
    'stock_quantity',
    'specifications',
    'published_at',
    'submitted_for_review_at',
    'moderated_at',
    'moderated_by',
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
            'store_id' => 'integer',
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'base_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'specifications' => 'array',
            'published_at' => 'datetime',
            'submitted_for_review_at' => 'datetime',
            'moderated_at' => 'datetime',
            'moderated_by' => 'integer',
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
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
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
    public function scopeFromActiveStore(Builder $query): Builder
    {
        return self::applyActiveStoreFilter($query);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyActiveStoreFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $storeScope): void {
            $storeScope
                ->whereNull('products.store_id')
                ->orWhereHas('store', fn (Builder $storeQuery): Builder => $storeQuery->where('status', StoreStatus::Active->value));
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyActiveCategoryCountFilter(Builder $query): Builder
    {
        return self::applyActiveStoreFilter($query->where('products.status', ProductStatus::Active->value));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('products.status', ProductStatus::Active->value)
            ->whereHas('category', fn (Builder $catQuery): Builder => $catQuery->where('is_active', true))
            ->fromActiveStore();
    }

    public function isStorefrontVisible(): bool
    {
        $this->loadMissing(['category', 'store']);

        return $this->status === ProductStatus::Active
            && $this->category->is_active
            && (! $this->store instanceof Store || $this->store->isActive());
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
