<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Admin\ImportInventoryCsvRequest;
use App\Models\User;

class ImportSellerInventoryCsvRequest extends ImportInventoryCsvRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasApprovedStore();
    }
}
