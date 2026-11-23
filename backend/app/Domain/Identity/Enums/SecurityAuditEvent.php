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
    case StaffPermissionsUpdated = 'admin.staff_permissions_updated';
    case SellerApplicationSubmitted = 'seller.application_submitted';
    case SellerUnderReview = 'admin.seller_under_review';
    case SellerApproved = 'admin.seller_approved';
    case SellerRejected = 'admin.seller_rejected';
    case SellerSuspended = 'admin.seller_suspended';
    case SellerReactivated = 'admin.seller_reactivated';
    case SellerDocumentDownloaded = 'seller.document_downloaded';
    case SellerStoreUpdated = 'seller.store_updated';
    case SellerProductSubmittedForReview = 'seller.product_submitted';
    case AdminProductApproved = 'admin.product_approved';
    case AdminProductRejected = 'admin.product_rejected';
    case SellerInventoryAdjusted = 'seller.inventory_adjusted';
    case SellerInventoryBulkImported = 'seller.inventory_imported';
    case SellerOrderStatusUpdated = 'seller.order_status_updated';
    case AdminSellerOrderStatusUpdated = 'admin.seller_order_status_updated';
    case SellerPayoutRequested = 'seller.payout_requested';
    case AdminPayoutUpdated = 'admin.payout_updated';
    case AdminCommissionRuleUpdated = 'admin.commission_rule_updated';

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
            self::StaffPermissionsUpdated => 'Super Admin updated staff permissions',
            self::SellerApplicationSubmitted => 'Seller onboarding application submitted',
            self::SellerUnderReview => 'Seller application moved to review',
            self::SellerApproved => 'Super Admin approved seller account',
            self::SellerRejected => 'Super Admin rejected seller application',
            self::SellerSuspended => 'Super Admin suspended seller account',
            self::SellerReactivated => 'Super Admin reactivated seller account',
            self::SellerDocumentDownloaded => 'Seller verification document accessed',
            self::SellerStoreUpdated => 'Seller updated store profile details',
            self::SellerProductSubmittedForReview => 'Seller submitted product for moderation review',
            self::AdminProductApproved => 'Admin approved seller product listing live',
            self::AdminProductRejected => 'Admin requested changes on product listing',
            self::SellerInventoryAdjusted => 'Seller adjusted store inventory stock',
            self::SellerInventoryBulkImported => 'Seller imported store inventory CSV',
            self::SellerOrderStatusUpdated => 'Seller updated store fulfillment status',
            self::AdminSellerOrderStatusUpdated => 'Admin updated seller suborder status',
            self::SellerPayoutRequested => 'Seller requested a payout withdrawal',
            self::AdminPayoutUpdated => 'Super Admin updated seller payout status',
            self::AdminCommissionRuleUpdated => 'Super Admin updated marketplace commission rule',
        };
    }
}
