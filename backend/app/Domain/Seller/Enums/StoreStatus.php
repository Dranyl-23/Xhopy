<?php

namespace App\Domain\Seller\Enums;

enum StoreStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
