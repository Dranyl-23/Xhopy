<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\Enums\OrderStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $seller_order_id
 * @property int|null $actor_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property-read Order $order
 * @property-read SellerOrder|null $sellerOrder
 * @property-read User|null $actor
 */
#[Fillable(['order_id', 'seller_order_id', 'actor_id', 'from_status', 'to_status', 'reason', 'created_at'])]
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'seller_order_id' => 'integer',
            'actor_id' => 'integer',
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'created_at' => 'datetime',
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
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
