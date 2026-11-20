<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageCatalog() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $productId = $product?->id;

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'seller_name' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:220',
                'alpha_dash',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'sku' => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'short_description' => ['nullable', 'string', 'max:400'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'currency' => ['required', 'string', Rule::in(['PHP'])],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'specifications' => ['nullable', 'array', 'max:30'],
            'specifications.*' => ['nullable', 'string', 'max:200'],
            'variants' => ['nullable', 'array', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.title' => ['required', 'string', 'max:160'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.options' => ['nullable', 'array', 'max:10'],
            'variants.*.options.*' => ['nullable', 'string', 'max:100'],
            'variants.*.is_default' => ['boolean'],
            'variants.*.is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                /** @var Product|null $product */
                $product = $this->route('product');
                $variants = $this->input('variants', []);
                if (! is_array($variants)) {
                    return;
                }

                foreach ($variants as $index => $variantRow) {
                    if (! is_array($variantRow)) {
                        continue;
                    }

                    $sku = strtoupper(trim((string) ($variantRow['sku'] ?? '')));
                    $variantId = isset($variantRow['id']) ? (int) $variantRow['id'] : null;

                    if ($sku === '') {
                        continue;
                    }

                    $duplicateQuery = ProductVariant::query()->where('sku', $sku);
                    if ($variantId !== null && $product instanceof Product) {
                        $duplicateQuery->where(function ($q) use ($variantId, $product): void {
                            $q->where('id', '!=', $variantId)
                                ->orWhere('product_id', '!=', $product->id);
                        });
                    }

                    if ($duplicateQuery->exists()) {
                        $validator->errors()->add("variants.{$index}.sku", 'The variant SKU has already been taken.');
                    }
                }
            },
        ];
    }
}
