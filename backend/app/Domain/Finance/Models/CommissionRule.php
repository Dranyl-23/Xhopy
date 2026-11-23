<?php

namespace App\Domain\Finance\Models;

use App\Domain\Seller\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $store_id
 * @property int $rate_bps
 * @property int $hold_days
 * @property int $minimum_payout_centavos
 * @property bool $is_active
 * @property CarbonImmutable $effective_from
 * @property int|null $created_by
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Store|null $store
 * @property-read User|null $creator
 */
#[Fillable([
    'store_id',
    'rate_bps',
    'hold_days',
    'minimum_payout_centavos',
    'is_active',
    'effective_from',
    'created_by',
    'notes',
])]
class CommissionRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'rate_bps' => 'integer',
            'hold_days' => 'integer',
            'minimum_payout_centavos' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'created_by' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
