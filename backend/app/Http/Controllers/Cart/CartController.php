<?php

namespace App\Http\Controllers\Cart;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CatalogPresenter $catalogPresenter,
    ) {}

    public function index(Request $request): Response
    {
        $cart = $this->cartService->findExistingCart(
            $request->user(),
            $this->cartService->sessionToken($request, false),
        );

        $presentedCart = $this->cartService->presentCart($cart);

        $categories = Category::query()
            ->active()
            ->withCount([
                'products' => fn (Builder $query): Builder => Product::applyActiveCategoryCountFilter($query),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('cart/Index', [
            'cart' => $presentedCart,
            'cartSummary' => [
                'itemCount' => $presentedCart['itemCount'],
                'subtotalFormatted' => $presentedCart['itemCount'] > 0 ? $presentedCart['subtotalFormatted'] : null,
                'items' => array_slice($presentedCart['items'], 0, 5),
            ],
            'categories' => $categories->map(fn (Category $category): array => $this->catalogPresenter->categorySummary($category))->all(),
        ]);
    }

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $sessionToken = $request->user() === null
            ? $this->cartService->sessionToken($request, true)
            : null;

        $this->cartService->addItem(
            user: $request->user(),
            sessionToken: $sessionToken,
            productId: (int) $request->validated('product_id'),
            variantId: $request->validated('product_variant_id') !== null
                ? (int) $request->validated('product_variant_id')
                : null,
            quantity: (int) $request->validated('quantity'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to cart.')]);

        return back();
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->cartService->findExistingCart(
            $request->user(),
            $this->cartService->sessionToken($request, false),
        );

        if (! $cart instanceof Cart) {
            abort(403);
        }

        $this->cartService->updateItemQuantity(
            cart: $cart,
            item: $cartItem,
            quantity: (int) $request->validated('quantity'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cart updated.')]);

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $cart = $this->cartService->findExistingCart(
            $request->user(),
            $this->cartService->sessionToken($request, false),
        );

        if (! $cart instanceof Cart) {
            abort(403);
        }

        $this->cartService->removeItem($cart, $cartItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed from cart.')]);

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $cart = $this->cartService->findExistingCart(
            $request->user(),
            $this->cartService->sessionToken($request, false),
        );

        if ($cart instanceof Cart) {
            $this->cartService->clearCart($cart);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cart cleared.')]);

        return back();
    }
}
