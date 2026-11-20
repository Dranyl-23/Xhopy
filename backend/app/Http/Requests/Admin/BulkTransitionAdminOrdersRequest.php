<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkTransitionAdminOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageCatalog() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:processing,shipped,delivered'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
