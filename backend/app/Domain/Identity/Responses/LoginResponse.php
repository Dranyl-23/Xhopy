<?php

namespace App\Domain\Identity\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        $user = $request->user();
        $defaultHome = match (true) {
            $user instanceof User && $user->canManageOperations() => route('admin.operations.index', absolute: false),
            $user instanceof User && $user->canReviewSellers() => route('admin.sellers.index', absolute: false),
            $user instanceof User && $user->canManageCatalog() => route('admin.catalog.index', absolute: false),
            $user instanceof User && $user->isSeller() => route($user->hasApprovedStore() ? 'seller.dashboard' : 'seller.onboarding', absolute: false),
            default => route('dashboard', absolute: false),
        };

        return redirect()->intended($defaultHome);
    }
}
