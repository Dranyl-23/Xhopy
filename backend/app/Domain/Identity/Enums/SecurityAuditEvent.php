<?php

namespace App\Domain\Identity\Enums;

enum SecurityAuditEvent: string
{
    case Registered = 'auth.registered';
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case PasswordUpdated = 'auth.password_updated';
    case PasswordReset = 'auth.password_reset';
    case EmailVerified = 'auth.email_verified';
    case VerificationCodeSent = 'auth.verification_code_sent';
    case TwoFactorEnabled = 'auth.two_factor_enabled';
    case TwoFactorConfirmed = 'auth.two_factor_confirmed';
    case TwoFactorDisabled = 'auth.two_factor_disabled';
    case SessionRevoked = 'auth.session_revoked';
    case OtherSessionsRevoked = 'auth.other_sessions_revoked';
    case RoleUpdated = 'account.role_updated';
    case AddressCreated = 'customer.address_created';
    case AddressUpdated = 'customer.address_updated';
    case AddressDeleted = 'customer.address_deleted';
    case OrderStatusUpdated = 'admin.order_status_updated';
    case OrdersBulkTransitioned = 'admin.orders_bulk_updated';
    case InventoryAdjusted = 'admin.inventory_adjusted';
    case InventoryBulkImported = 'admin.inventory_imported';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Account registered',
            self::Login => 'Signed in',
            self::LoginFailed => 'Failed sign-in attempt',
            self::Logout => 'Signed out',
            self::PasswordUpdated => 'Password changed',
            self::PasswordReset => 'Password reset via recovery link',
            self::EmailVerified => 'Email address verified',
            self::VerificationCodeSent => 'Email verification code sent',
            self::TwoFactorEnabled => 'Two-factor authentication initiated',
            self::TwoFactorConfirmed => 'Two-factor authentication enabled',
            self::TwoFactorDisabled => 'Two-factor authentication disabled',
            self::SessionRevoked => 'Session signed out remotely',
            self::OtherSessionsRevoked => 'All other sessions signed out',
            self::RoleUpdated => 'Account role updated',
            self::AddressCreated => 'Saved address added',
            self::AddressUpdated => 'Saved address updated',
            self::AddressDeleted => 'Saved address removed',
            self::OrderStatusUpdated => 'Admin updated order status',
            self::OrdersBulkTransitioned => 'Admin bulk-advanced orders',
            self::InventoryAdjusted => 'Admin adjusted inventory stock',
            self::InventoryBulkImported => 'Admin imported inventory CSV',
        };
    }
}
