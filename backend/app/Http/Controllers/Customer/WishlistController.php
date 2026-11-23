<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Domain\Customer\Models\WishlistItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function index(Request $request, CatalogPresenter $presenter): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $items = $user->wishlistItems()
            ->with([
                'product' => static fn ($query) => $query
                    ->with(['category', 'store', 'brand', 'images', 'variants']),
            ])
            ->orderByDesc('id')
            ->get()
            ->filter(static fn (WishlistItem $entry): bool => $entry->product->isStorefrontVisible())
            ->map(static fn (WishlistItem $entry): array => [
                'id' => $entry->id,
                'product_id' => $entry->product_id,
                'added_at_diff' => $entry->created_at->diffForHumans(),
                'product' => $presenter->productSummary($entry->product),
            ])
            ->values()
            ->all();

        return Inertia::render('wishlist/Index', [
            'items' => $items,
        ]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $existing = WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing instanceof WishlistItem) {
            Gate::forUser($user)->authorize('delete', $existing);
            $existing->delete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Removed :name from your wishlist.', ['name' => $product->name]),
            ]);

            return back();
        }

        if (! $product->isStorefrontVisible()) {
            abort(404);
        }

        WishlistItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'created_at' => Date::now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Saved :name to your wishlist.', ['name' => $product->name]),
        ]);

        return back();
    }

    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        Gate::forUser($user)->authorize('delete', $wishlistItem);

        $wishlistItem->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Item removed from your wishlist.'),
        ]);

        return back();
    }
}
