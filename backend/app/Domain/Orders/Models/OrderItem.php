<?php

namespace App\Domain\Orders\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int|null $seller_id
 * @property string $seller_name
 * @property string $product_name
 * @property string|null $variant_title
 * @property string $sku
 * @property string $currency
 * @property string $unit_price
 * @property int $quantity
 * @property string $line_total
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 */
#[Fillable([
    'order_id',
    'product_id',
    'product_variant_id',
    'seller_id',
    'seller_name',
    'product_name',
    'variant_title',
    'sku',
    'currency',
    'unit_price',
    'quantity',
    'line_total',
])]
class OrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'seller_id' => 'integer',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
