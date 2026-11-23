<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in(array_map(
                    static fn (ProductStatus $status): string => $status->value,
                    ProductStatus::adminModerationTargets(),
                )),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
