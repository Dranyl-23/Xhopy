<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionRuleRequest extends FormRequest
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
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'hold_days' => ['required', 'integer', 'min:0', 'max:90'],
            'minimum_payout_amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
