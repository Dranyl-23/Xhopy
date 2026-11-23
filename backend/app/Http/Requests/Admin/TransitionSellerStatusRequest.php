<?php

namespace App\Http\Requests\Admin;

use App\Domain\Seller\Enums\SellerStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSellerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canReviewSellers();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(SellerStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
