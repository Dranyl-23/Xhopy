<?php

namespace App\Models;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Customer\Models\WishlistItem;
use App\Domain\Identity\Actions\SendEmailVerificationCodeAction;
use App\Domain\Identity\Enums\StaffPermission;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\EmailVerificationCode;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property UserRole $role
 * @property list<string>|null $staff_permissions
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'staff_permissions' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function canManageCatalog(): bool
    {
        return $this->hasStaffPermission(StaffPermission::CatalogManage);
    }

    public function canManageOrders(): bool
    {
        return $this->hasStaffPermission(StaffPermission::OrdersManage);
    }

    public function canManageInventory(): bool
    {
        return $this->hasStaffPermission(StaffPermission::InventoryManage);
    }

    public function canReviewSellers(): bool
    {
        return $this->hasStaffPermission(StaffPermission::SellersReview);
    }

    public function canManageOperations(): bool
    {
        return $this->isSuperAdmin()
            || $this->canManageOrders()
            || $this->canManageInventory();
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->isSuperAdmin()
            || $this->canManageCatalog()
            || $this->canManageOrders()
            || $this->canManageInventory()
            || $this->canReviewSellers();
    }

    /**
     * @return list<string>
     */
    public function resolvedStaffPermissions(): array
    {
        if ($this->isAdmin()) {
            return array_map(static fn (StaffPermission $permission): string => $permission->value, StaffPermission::cases());
        }

        if ($this->role !== UserRole::Staff) {
            return [];
        }

        if (is_array($this->staff_permissions)) {
            return array_values(array_filter(
                array_map(static fn (mixed $value): string => (string) $value, $this->staff_permissions),
                static fn (string $value): bool => StaffPermission::tryFrom($value) !== null,
            ));
        }

        return StaffPermission::defaultLegacyPermissions();
    }

    public function hasStaffPermission(StaffPermission $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->role !== UserRole::Staff) {
            return false;
        }

        return in_array($permission->value, $this->resolvedStaffPermissions(), true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdmin();
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
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
    public function storeMemberships(): HasMany
    {
        return $this->hasMany(StoreMembership::class);
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function ownedStores(): HasMany
    {
        return $this->hasMany(Store::class, 'owner_user_id');
    }

    public function primaryStore(): ?Store
    {
        /** @var StoreMembership|null $membership */
        $membership = $this->storeMemberships()->with('store')->orderBy('id')->first();
        if ($membership instanceof StoreMembership) {
            return $membership->store;
        }

        /** @var Store|null $ownedStore */
        $ownedStore = $this->ownedStores()->orderBy('id')->first();

        return $ownedStore;
    }

    public function approvedStore(): ?Store
    {
        if (! $this->isSeller()) {
            return null;
        }

        /** @var SellerProfile|null $profile */
        $profile = $this->sellerProfile()->with('store')->first();

        if (
            ! $profile instanceof SellerProfile
            || $profile->status !== SellerStatus::Approved
            || ! $profile->store instanceof Store
            || $profile->store->status !== StoreStatus::Active
        ) {
            return null;
        }

        return $profile->store;
    }

    public function hasApprovedStore(): bool
    {
        return $this->approvedStore() instanceof Store;
    }

    /**
     * @return HasMany<EmailVerificationCode, $this>
     */
    public function emailVerificationCodes(): HasMany
    {
        return $this->hasMany(EmailVerificationCode::class);
    }

    public function isUsingGmailAddress(): bool
    {
        return SendEmailVerificationCodeAction::isGmailAddress($this->email);
    }

    public function sendEmailVerificationCode(?string $mailer = null, ?bool $requireGmail = null): EmailVerificationCode
    {
        return app(SendEmailVerificationCodeAction::class)->execute($this, $mailer, $requireGmail);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasMany<WishlistItem, $this>
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * @return HasMany<SecurityAuditLog, $this>
     */
    public function securityAuditLogs(): HasMany
    {
        return $this->hasMany(SecurityAuditLog::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasMfaEnabled(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication() || $this->passkeys()->exists();
    }
}
