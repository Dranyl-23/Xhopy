<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSellerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:approved,paid,rejected'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
