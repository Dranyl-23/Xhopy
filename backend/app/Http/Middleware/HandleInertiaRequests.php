<?php

namespace App\Http\Middleware;

use App\Domain\Customer\Models\WishlistItem;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $hasMfa = $user instanceof User ? $user->hasMfaEnabled() : false;
        $canAccessAdmin = $user instanceof User && $user->canAccessAdminPanel();
        $canManageCatalog = $user instanceof User && $user->canManageCatalog();
        $canManageOrders = $user instanceof User && $user->canManageOrders();
        $canManageInventory = $user instanceof User && $user->canManageInventory();
        $canReviewSellers = $user instanceof User && $user->canReviewSellers();
        $canManageOperations = $user instanceof User && $user->canManageOperations();
        $wishlistProductIds = $user instanceof User
            ? WishlistItem::query()
                ->where('user_id', $user->id)
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all()
            : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'role' => $user instanceof User ? $user->role->value : null,
                'roleLabel' => $user instanceof User ? $user->role->label() : null,
                'canAccessAdmin' => $canAccessAdmin,
                'canManageCatalog' => $canManageCatalog,
                'canManageOrders' => $canManageOrders,
                'canManageInventory' => $canManageInventory,
                'canReviewSellers' => $canReviewSellers,
                'canManageOperations' => $canManageOperations,
                'canManageUsers' => $user instanceof User && $user->isSuperAdmin(),
                'isSeller' => $user instanceof User && $user->isSeller(),
                'isApprovedSeller' => $user instanceof User && $user->hasApprovedStore(),
                'hasMfaEnabled' => $hasMfa,
                'mfaRecommended' => $canAccessAdmin && ! $hasMfa,
            ],
            'wishlistProductIds' => $wishlistProductIds,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
