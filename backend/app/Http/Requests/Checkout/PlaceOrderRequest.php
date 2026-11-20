<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1', 'exists:customer_addresses,id'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
