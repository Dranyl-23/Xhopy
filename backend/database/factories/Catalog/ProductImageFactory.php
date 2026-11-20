<?php

namespace Database\Factories\Catalog;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_id' => null,
            'disk' => 'local',
            'path' => 'catalog/products/1/sample.png',
            'alt_text' => fake()->sentence(5),
            'sort_order' => 0,
            'is_primary' => false,
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
        ];
    }
}
