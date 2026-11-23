<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\CatalogSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'brand' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'store' => ['nullable', 'string', 'max:140', 'alpha_dash'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::enum(CatalogSort::class)],
            'per_page' => ['nullable', 'integer', Rule::in([12, 24, 48])],
        ];
    }
}
