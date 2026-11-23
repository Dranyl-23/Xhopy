<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\LedgerEntryType;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only immutable seller financial ledger entry.
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $seller_order_id
 * @property int|null $seller_payout_id
 * @property int|null $actor_id
 * @property LedgerEntryType $entry_type
 * @property int $amount_centavos
 * @property string $currency
 * @property string $description
 * @property string $idempotency_key
 * @property CarbonImmutable $created_at
 * @property-read Store $store
 * @property-read SellerOrder|null $sellerOrder
 * @property-read SellerPayout|null $payout
 * @property-read User|null $actor
 */
#[Fillable([
    'store_id',
    'seller_order_id',
    'seller_payout_id',
    'actor_id',
    'entry_type',
    'amount_centavos',
    'currency',
    'description',
    'idempotency_key',
    'created_at',
])]
class SellerLedgerEntry extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'seller_order_id' => 'integer',
            'seller_payout_id' => 'integer',
            'actor_id' => 'integer',
            'entry_type' => LedgerEntryType::class,
            'amount_centavos' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<SellerPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'seller_payout_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
