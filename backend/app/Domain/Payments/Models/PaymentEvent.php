<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $payment_id
 * @property int|null $order_id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $event_type
 * @property string $status
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $processed_at
 * @property CarbonImmutable $created_at
 * @property-read Payment|null $payment
 * @property-read Order|null $order
 */
#[Fillable([
    'payment_id',
    'order_id',
    'provider',
    'provider_event_id',
    'event_type',
    'status',
    'payload',
    'processed_at',
    'created_at',
])]
class PaymentEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'order_id' => 'integer',
            'payload' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
