<?php

namespace Database\Factories\Catalog;

use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(2);
        $rawName = is_array($words) ? implode(' ', $words) : $words;
        $name = ucwords($rawName);

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->sentence(8),
            'image_path' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
