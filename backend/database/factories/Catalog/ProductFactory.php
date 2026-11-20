<?php

namespace Database\Factories\Catalog;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = fake()->unique()->words(3);
        $rawName = is_array($words) ? implode(' ', $words) : $words;
        $name = ucwords($rawName);
        $basePrice = fake()->randomFloat(2, 450, 9500);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'seller_id' => null,
            'seller_name' => 'XHopy Official',
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'sku' => 'XH-'.strtoupper(Str::random(3)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraphs(2, true),
            'status' => ProductStatus::Active,
            'is_featured' => false,
            'currency' => 'PHP',
            'base_price' => number_format($basePrice, 2, '.', ''),
            'compare_at_price' => null,
            'stock_quantity' => 15,
            'specifications' => [
                'Warranty' => '1 Year Official Support',
            ],
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => [
            'is_featured' => true,
        ]);
    }
}
