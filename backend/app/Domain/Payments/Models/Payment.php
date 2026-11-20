<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property string|null $provider_payment_id
 * @property string|null $checkout_url
 * @property PaymentStatus $status
 * @property string $currency
 * @property string $amount
 * @property int $amount_centavos
 * @property string|null $payment_method_type
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $expires_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 * @property-read User $user
 */
#[Fillable([
    'order_id',
    'user_id',
    'provider',
    'provider_reference',
    'provider_payment_id',
    'checkout_url',
    'status',
    'currency',
    'amount',
    'amount_centavos',
    'payment_method_type',
    'paid_at',
    'expires_at',
    'metadata',
])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'user_id' => 'integer',
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'amount_centavos' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
