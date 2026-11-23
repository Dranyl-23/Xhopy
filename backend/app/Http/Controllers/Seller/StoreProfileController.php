<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Seller\Actions\UpdateSellerStoreAction;
use App\Domain\Seller\Services\SellerWorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateSellerStoreRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreProfileController extends Controller
{
    public function edit(Request $request, SellerWorkspaceService $workspace): Response
    {
        ['profile' => $profile, 'store' => $store] = $workspace->requireApprovedWorkspace($request->user());

        return Inertia::render('seller/StoreProfile', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description ?? '',
                'contact_email' => $store->contact_email ?? '',
                'contact_phone' => $store->contact_phone ?? '',
                'status' => $store->status->value,
                'status_label' => $store->status->label(),
                'products_count' => $store->products()->count(),
            ],
            'profile' => [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'business_type' => $profile->business_type,
                'approved_at' => $profile->approved_at?->toDayDateTimeString(),
            ],
        ]);
    }

    public function update(
        UpdateSellerStoreRequest $request,
        SellerWorkspaceService $workspace,
        UpdateSellerStoreAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        ['store' => $store] = $workspace->requireApprovedWorkspace($actor);

        $action->execute(
            actor: $actor,
            store: $store,
            data: $request->validated(),
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Store profile updated.'),
        ]);

        return to_route('seller.store.edit');
    }
}
