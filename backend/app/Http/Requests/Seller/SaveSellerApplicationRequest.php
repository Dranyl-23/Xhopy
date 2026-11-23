<?php

namespace App\Http\Requests\Seller;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSellerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ! $user->canAccessAdminPanel();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'min:2', 'max:120'],
            'store_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'store_description' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone' => ['required', 'string', 'min:6', 'max:40'],
            'business_name' => ['required', 'string', 'min:2', 'max:180'],
            'business_type' => [
                'required',
                'string',
                Rule::in(['sole_proprietorship', 'partnership', 'corporation', 'cooperative', 'individual']),
            ],
            'business_address' => ['required', 'string', 'min:6', 'max:400'],
            'tax_identifier' => ['nullable', 'string', 'max:80'],
            'payout_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
