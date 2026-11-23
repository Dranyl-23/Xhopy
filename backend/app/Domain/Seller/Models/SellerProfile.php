<?php

namespace App\Domain\Seller\Models;

use App\Domain\Seller\Enums\SellerStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $store_id
 * @property string $business_name
 * @property string $business_type
 * @property string $contact_phone
 * @property string $business_address
 * @property string|null $tax_identifier
 * @property string|null $payout_notes
 * @property SellerStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Store|null $store
 * @property-read User|null $reviewer
 * @property-read Collection<int, SellerVerificationDocument> $documents
 * @property-read Collection<int, SellerStatusHistory> $statusHistory
 */
#[Fillable([
    'user_id',
    'store_id',
    'business_name',
    'business_type',
    'contact_phone',
    'business_address',
    'tax_identifier',
    'payout_notes',
    'status',
    'rejection_reason',
    'reviewed_by',
    'submitted_at',
    'reviewed_at',
    'approved_at',
    'suspended_at',
])]
class SellerProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'store_id' => 'integer',
            'reviewed_by' => 'integer',
            'status' => SellerStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
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
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<SellerVerificationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SellerVerificationDocument::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<SellerStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(SellerStatusHistory::class)->orderByDesc('id');
    }
}
