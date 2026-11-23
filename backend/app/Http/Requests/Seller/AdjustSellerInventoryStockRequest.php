<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Admin\AdjustInventoryStockRequest;
use App\Models\User;

class AdjustSellerInventoryStockRequest extends AdjustInventoryStockRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasApprovedStore();
    }
}
