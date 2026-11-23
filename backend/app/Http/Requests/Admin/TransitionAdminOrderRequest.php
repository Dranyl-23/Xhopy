<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransitionAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageOrders() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:processing,shipped,delivered,cancelled,expired,refunded'],
            'reason' => ['nullable', 'string', 'max:255'],
            'shipping_carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ];
    }
}
