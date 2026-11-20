<?php

namespace Database\Factories\Catalog;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $color = fake()->safeColorName();
        $price = fake()->randomFloat(2, 500, 9500);

        return [
            'product_id' => Product::factory(),
            'title' => ucfirst($color).' Edition',
            'sku' => 'XHV-'.strtoupper(Str::random(3)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'price' => number_format($price, 2, '.', ''),
            'compare_at_price' => null,
            'currency' => 'PHP',
            'stock_quantity' => 10,
            'options' => [
                'Color' => ucfirst($color),
            ],
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
