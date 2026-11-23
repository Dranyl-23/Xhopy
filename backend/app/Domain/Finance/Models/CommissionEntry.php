<?php

namespace App\Domain\Finance\Models;

use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Seller\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $seller_order_id
 * @property int $store_id
 * @property int|null $commission_rule_id
 * @property int $gross_amount_centavos
 * @property int $rate_bps
 * @property int $commission_centavos
 * @property int $net_seller_centavos
 * @property string $status
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $reversed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SellerOrder $sellerOrder
 * @property-read Store $store
 * @property-read CommissionRule|null $rule
 */
#[Fillable([
    'seller_order_id',
    'store_id',
    'commission_rule_id',
    'gross_amount_centavos',
    'rate_bps',
    'commission_centavos',
    'net_seller_centavos',
    'status',
    'recorded_at',
    'reversed_at',
])]
class CommissionEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_order_id' => 'integer',
            'store_id' => 'integer',
            'commission_rule_id' => 'integer',
            'gross_amount_centavos' => 'integer',
            'rate_bps' => 'integer',
            'commission_centavos' => 'integer',
            'net_seller_centavos' => 'integer',
            'recorded_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<CommissionRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}
