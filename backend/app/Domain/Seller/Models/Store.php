<?php

namespace App\Domain\Seller\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Seller\Enums\StoreStatus;
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
 * @property int|null $owner_user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property StoreStatus $status
 * @property bool $is_official
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $owner
 * @property-read SellerProfile|null $sellerProfile
 * @property-read Collection<int, StoreMembership> $memberships
 * @property-read Collection<int, Product> $products
 */
#[Fillable([
    'owner_user_id',
    'name',
    'slug',
    'description',
    'contact_email',
    'contact_phone',
    'status',
    'is_official',
])]
class Store extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'status' => StoreStatus::class,
            'is_official' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasOne<SellerProfile, $this>
     */
    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    /**
     * @return HasMany<StoreMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(StoreMembership::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isActive(): bool
    {
        return $this->status === StoreStatus::Active;
    }
}
