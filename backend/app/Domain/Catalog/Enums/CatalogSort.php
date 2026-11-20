<?php

namespace App\Domain\Catalog\Enums;

enum CatalogSort: string
{
    case Featured = 'featured';
    case Newest = 'newest';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case NameAsc = 'name_asc';

    public function label(): string
    {
        return match ($this) {
            self::Featured => 'Featured',
            self::Newest => 'Newest arrivals',
            self::PriceAsc => 'Price: Low to High',
            self::PriceDesc => 'Price: High to Low',
            self::NameAsc => 'Name: A to Z',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
