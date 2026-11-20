<?php

namespace App\Models;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Actions\SendEmailVerificationCodeAction;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\EmailVerificationCode;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Orders\Models\Order;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function canManageCatalog(): bool
    {
        return $this->role->canManageCatalog();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
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
