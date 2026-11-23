<?php

namespace App\Http\Requests\Seller;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveSellerProductRequest extends SaveProductRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User || ! $user->hasApprovedStore()) {
            return false;
        }

        $routeProduct = $this->route('product');
        if ($routeProduct instanceof Product) {
            return Gate::forUser($user)->allows('manageOwned', $routeProduct);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Sellers cannot inject store_id, seller_name, is_featured, or self-publish Active listings.
        unset($rules['seller_name'], $rules['is_featured']);

        $rules['status'] = [
            'required',
            Rule::in(array_map(
                static fn (ProductStatus $status): string => $status->value,
                ProductStatus::sellerSelectableCases(),
            )),
        ];

        return $rules;
    }
}
