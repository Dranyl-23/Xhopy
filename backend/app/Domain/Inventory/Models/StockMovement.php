<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int|null $order_id
 * @property int|null $actor_id
 * @property StockMovementType $type
 * @property int $quantity_delta
 * @property int $stock_after
 * @property string|null $reference
 * @property CarbonImmutable $created_at
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 * @property-read Order|null $order
 * @property-read User|null $actor
 */
#[Fillable([
    'product_id',
    'product_variant_id',
    'order_id',
    'actor_id',
    'type',
    'quantity_delta',
    'stock_after',
    'reference',
    'created_at',
])]
class StockMovement extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'order_id' => 'integer',
            'actor_id' => 'integer',
            'type' => StockMovementType::class,
            'quantity_delta' => 'integer',
            'stock_after' => 'integer',
            'created_at' => 'datetime',
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
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
