<?php

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    public function canManageCatalog(): bool
    {
        return match ($this) {
            self::Staff, self::Admin => true,
            self::Customer => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Staff => 'Staff',
            self::Admin => 'Administrator',
        };
    }
}
