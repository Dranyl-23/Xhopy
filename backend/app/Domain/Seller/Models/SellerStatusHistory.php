<?php

namespace App\Domain\Seller\Models;

use App\Domain\Seller\Enums\SellerStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $seller_profile_id
 * @property int|null $actor_id
 * @property SellerStatus|null $from_status
 * @property SellerStatus $to_status
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property-read SellerProfile $sellerProfile
 * @property-read User|null $actor
 */
#[Fillable([
    'seller_profile_id',
    'actor_id',
    'from_status',
    'to_status',
    'reason',
    'created_at',
])]
class SellerStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'seller_status_history';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_profile_id' => 'integer',
            'actor_id' => 'integer',
            'from_status' => SellerStatus::class,
            'to_status' => SellerStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SellerProfile, $this>
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
