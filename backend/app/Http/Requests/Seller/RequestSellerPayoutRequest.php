<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class RequestSellerPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSeller() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'payout_method' => ['required', 'string', 'in:bank_transfer,gcash,maya'],
            'payout_destination' => ['required', 'string', 'max:255'],
            'request_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
