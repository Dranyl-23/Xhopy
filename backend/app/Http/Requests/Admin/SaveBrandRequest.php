<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBrandRequest extends FormRequest
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
        /** @var Brand|null $brand */
        $brand = $this->route('brand');
        $brandId = $brand?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('brands', 'slug')->ignore($brandId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
