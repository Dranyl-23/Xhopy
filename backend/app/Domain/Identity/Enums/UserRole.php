<?php

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Seller = 'seller';
    case Staff = 'staff';
    case Admin = 'admin';

    public function canManageCatalog(): bool
    {
        return match ($this) {
            self::Staff, self::Admin => true,
            self::Customer, self::Seller => false,
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return match ($this) {
            self::Staff, self::Admin => true,
            self::Customer, self::Seller => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Seller => 'Seller',
            self::Staff => 'Admin Staff',
            self::Admin => 'Super Admin',
        };
    }
}
