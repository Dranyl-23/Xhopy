<?php

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $payout_number
 * @property int $store_id
 * @property int $requested_by
 * @property int|null $reviewed_by
 * @property PayoutStatus $status
 * @property string $currency
 * @property int $amount_centavos
 * @property string $payout_method
 * @property string $payout_destination
 * @property string|null $request_notes
 * @property string|null $review_notes
 * @property string|null $external_reference
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Store $store
 * @property-read User $requester
 * @property-read User|null $reviewer
 */
#[Fillable([
    'payout_number',
    'store_id',
    'requested_by',
    'reviewed_by',
    'status',
    'currency',
    'amount_centavos',
    'payout_method',
    'payout_destination',
    'request_notes',
    'review_notes',
    'external_reference',
    'requested_at',
    'approved_at',
    'paid_at',
    'rejected_at',
])]
class SellerPayout extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'requested_by' => 'integer',
            'reviewed_by' => 'integer',
            'status' => PayoutStatus::class,
            'amount_centavos' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
