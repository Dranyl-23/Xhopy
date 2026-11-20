<?php

namespace App\Domain\Catalog\Enums;

enum CampaignPlacement: string
{
    case Announcement = 'announcement';
    case DailyInterestFeatured = 'daily_interest_featured';
    case DailyInterestTile = 'daily_interest_tile';

    public function label(): string
    {
        return match ($this) {
            self::Announcement => 'Announcement Bar',
            self::DailyInterestFeatured => 'Daily Interest Featured Story',
            self::DailyInterestTile => 'Daily Interest Supporting Tile',
        };
    }
}
