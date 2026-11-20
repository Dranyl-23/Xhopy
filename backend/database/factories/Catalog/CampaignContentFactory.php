<?php

namespace Database\Factories\Catalog;

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Models\CampaignContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignContent>
 */
class CampaignContentFactory extends Factory
{
    protected $model = CampaignContent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placement' => CampaignPlacement::DailyInterestTile,
            'tag' => 'Spotlight',
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(12),
            'cta_label' => 'Explore Collection',
            'cta_href' => '/catalog',
            'category_id' => null,
            'image_path' => null,
            'image_alt' => null,
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
