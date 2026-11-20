<?php

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case Reserved = 'reserved';
    case Released = 'released';
    case Adjusted = 'adjusted';
    case Fulfilled = 'fulfilled';
}
