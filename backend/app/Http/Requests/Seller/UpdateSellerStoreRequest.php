<?php

namespace App\Http\Requests\Seller;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasApprovedStore();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
