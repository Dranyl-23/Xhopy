<?php

namespace App\Domain\Orders\Models;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $order_number
 * @property int $user_id
 * @property int|null $customer_address_id
 * @property string $idempotency_key
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property string $currency
 * @property string $subtotal_amount
 * @property string $shipping_amount
 * @property string $discount_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $shipping_recipient_name
 * @property string $shipping_phone
 * @property string $shipping_country_code
 * @property string $shipping_province
 * @property string $shipping_city
 * @property string $shipping_barangay
 * @property string $shipping_postal_code
 * @property string $shipping_address_line_1
 * @property string|null $shipping_address_line_2
 * @property string|null $shipping_delivery_notes
 * @property string|null $notes
 * @property string|null $shipping_carrier
 * @property string|null $tracking_number
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read CustomerAddress|null $customerAddress
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderStatusHistory> $statusHistory
 * @property-read Collection<int, Payment> $payments
 * @property-read Payment|null $latestPayment
 */
#[Fillable([
    'order_number',
    'user_id',
    'customer_address_id',
    'idempotency_key',
    'status',
    'payment_status',
    'currency',
    'subtotal_amount',
    'shipping_amount',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'shipping_recipient_name',
    'shipping_phone',
    'shipping_country_code',
    'shipping_province',
    'shipping_city',
    'shipping_barangay',
    'shipping_postal_code',
    'shipping_address_line_1',
    'shipping_address_line_2',
    'shipping_delivery_notes',
    'notes',
    'shipping_carrier',
    'tracking_number',
    'placed_at',
    'paid_at',
    'shipped_at',
    'delivered_at',
    'cancelled_at',
    'refunded_at',
    'expires_at',
])]
class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'customer_address_id' => 'integer',
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CustomerAddress, $this>
     */
    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('id');
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function formattedShippingAddress(): string
    {
        $parts = array_filter([
            $this->shipping_address_line_1,
            $this->shipping_address_line_2,
            $this->shipping_barangay,
            $this->shipping_city,
            $this->shipping_province,
            $this->shipping_postal_code,
            $this->shipping_country_code,
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(', ', $parts);
    }
}
