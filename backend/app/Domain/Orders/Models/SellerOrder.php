<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Seller\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int $store_id
 * @property string $seller_order_number
 * @property string $seller_name
 * @property bool $is_official_store
 * @property OrderStatus $status
 * @property string $currency
 * @property string $subtotal_amount
 * @property string $shipping_amount
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string|null $shipping_carrier
 * @property string|null $tracking_number
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read Store $store
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusHistory> $statusHistory
 */
#[Fillable([
    'order_id',
    'store_id',
    'seller_order_number',
    'seller_name',
    'is_official_store',
    'status',
    'currency',
    'subtotal_amount',
    'shipping_amount',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'shipping_carrier',
    'tracking_number',
    'paid_at',
    'shipped_at',
    'delivered_at',
    'cancelled_at',
    'refunded_at',
])]
class SellerOrder extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'store_id' => 'integer',
            'is_official_store' => 'boolean',
            'status' => OrderStatus::class,
            'subtotal_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
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
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'seller_order_id')->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'seller_order_id')->orderByDesc('id');
    }
}
