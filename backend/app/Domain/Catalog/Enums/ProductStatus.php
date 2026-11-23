<?php

namespace App\Domain\Catalog\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::Active => 'Active',
            self::Rejected => 'Changes Requested',
            self::Archived => 'Archived',
        };
    }

    /**
     * Status choices a seller may select directly when saving their product.
     *
     * @return list<self>
     */
    public static function sellerSelectableCases(): array
    {
        return [
            self::Draft,
            self::PendingReview,
            self::Archived,
        ];
    }

    /**
     * Target statuses an admin catalog moderator may assign during review.
     *
     * @return list<self>
     */
    public static function adminModerationTargets(): array
    {
        return [
            self::Active,
            self::Rejected,
            self::Archived,
        ];
    }
}
