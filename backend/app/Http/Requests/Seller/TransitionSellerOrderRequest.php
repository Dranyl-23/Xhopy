<?php

namespace App\Http\Requests\Seller;

use App\Domain\Orders\Models\SellerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TransitionSellerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sellerOrder = $this->route('sellerOrder');
        if (! $sellerOrder instanceof SellerOrder) {
            return false;
        }

        return Gate::allows('fulfillOwned', $sellerOrder);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:processing,shipped,delivered'],
            'reason' => ['nullable', 'string', 'max:255'],
            'shipping_carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ];
    }
}
