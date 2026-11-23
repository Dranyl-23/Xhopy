<?php

namespace App\Domain\Identity\Enums;

enum StaffPermission: string
{
    case CatalogManage = 'catalog.manage';
    case OrdersManage = 'orders.manage';
    case InventoryManage = 'inventory.manage';
    case SellersReview = 'sellers.review';

    public function label(): string
    {
        return match ($this) {
            self::CatalogManage => 'Manage Catalog & Campaigns',
            self::OrdersManage => 'Manage Orders & Fulfillment',
            self::InventoryManage => 'Manage Inventory & CSV',
            self::SellersReview => 'Review Seller Applications',
        };
    }

    /**
     * @return list<string>
     */
    public static function defaultLegacyPermissions(): array
    {
        return [
            self::CatalogManage->value,
            self::OrdersManage->value,
            self::InventoryManage->value,
        ];
    }
}
