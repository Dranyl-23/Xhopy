<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Enums\CampaignPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCampaignContentRequest extends FormRequest
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
        return [
            'placement' => ['required', Rule::enum(CampaignPlacement::class)],
            'tag' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_href' => ['nullable', 'string', 'max:255', 'regex:/^(?:\/[^\/\\\\]|#$|#[A-Za-z0-9_-]+)/'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
