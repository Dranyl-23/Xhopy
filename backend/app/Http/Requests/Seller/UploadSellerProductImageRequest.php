<?php

namespace App\Http\Requests\Seller;

use App\Domain\Catalog\Models\Product;
use App\Http\Requests\Admin\UploadProductImageRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UploadSellerProductImageRequest extends UploadProductImageRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $product = $this->route('product');

        return $user instanceof User
            && $product instanceof Product
            && Gate::forUser($user)->allows('manageOwned', $product);
    }
}
