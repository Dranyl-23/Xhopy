<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CartService
{
    private const string SESSION_TOKEN_KEY = 'xhopy_cart_token';

    public function __construct(
        private readonly CatalogPresenter $catalogPresenter,
    ) {}

    public function findExistingCart(?User $user, ?string $sessionToken): ?Cart
    {
        if ($user instanceof User) {
            return Cart::query()->where('user_id', $user->id)->first();
        }

        if (is_string($sessionToken) && trim($sessionToken) !== '') {
            return Cart::query()
                ->whereNull('user_id')
                ->where('session_token', trim($sessionToken))
                ->first();
        }

        return null;
    }

    public function sessionToken(Request $request, bool $createIfMissing = false): ?string
    {
        $existing = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (is_string($existing) && trim($existing) !== '') {
            return trim($existing);
        }

        if (! $createIfMissing) {
            return null;
        }

        $generated = Str::random(40);
        $request->session()->put(self::SESSION_TOKEN_KEY, $generated);

        return $generated;
    }

    public function resolveOrCreateCart(?User $user, ?string $sessionToken): Cart
    {
        $existing = $this->findExistingCart($user, $sessionToken);
        if ($existing instanceof Cart) {
            return $existing;
        }

        if ($user instanceof User) {
            return Cart::query()->create([
                'user_id' => $user->id,
                'currency' => 'PHP',
            ]);
        }

        $cleanToken = is_string($sessionToken) && trim($sessionToken) !== ''
            ? trim($sessionToken)
            : Str::random(40);

        return Cart::query()->create([
            'session_token' => $cleanToken,
            'currency' => 'PHP',
            'expires_at' => Date::now()->addDays(14),
        ]);
    }

    /**
     * Add a catalog product or variant to the cart with strict server-side stock and catalog validation.
     *
     * @throws ValidationException
     */
    public function addItem(
        ?User $user,
        ?string $sessionToken,
        int $productId,
        ?int $variantId,
        int $quantity,
    ): Cart {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        return DB::transaction(function () use ($user, $sessionToken, $productId, $variantId, $quantity): Cart {
            /** @var Product|null $product */
            $product = Product::query()
                ->published()
                ->with([
                    'variants' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
                ])
                ->whereKey($productId)
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is no longer available for purchase.',
                ]);
            }

            $activeVariants = $product->variants->where('is_active', true);
            $resolvedVariant = null;

            if ($activeVariants->isNotEmpty()) {
                $resolvedVariant = $variantId !== null
                    ? $activeVariants->firstWhere('id', $variantId)
                    : $product->effectiveDefaultVariant();

                if ($variantId !== null && $resolvedVariant === null) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => 'The selected product variant is invalid or unavailable.',
                    ]);
                }
            } elseif ($variantId !== null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected product variant does not belong to this product.',
                ]);
            }

            $availableStock = $resolvedVariant instanceof ProductVariant
                ? max(0, (int) $resolvedVariant->stock_quantity)
                : max(0, (int) $product->stock_quantity);

            if ($availableStock <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'This item is currently out of stock.',
                ]);
            }

            $cart = $this->resolveOrCreateCart($user, $sessionToken);
            $variantKey = $resolvedVariant instanceof ProductVariant ? $resolvedVariant->id : 0;

            /** @var CartItem|null $existingItem */
            $existingItem = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('variant_key', $variantKey)
                ->lockForUpdate()
                ->first();

            $targetQuantity = ($existingItem->quantity ?? 0) + $quantity;

            if ($targetQuantity > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$availableStock} unit(s) available in stock for {$product->name}.",
                ]);
            }

            if ($existingItem !== null) {
                $existingItem->forceFill(['quantity' => $targetQuantity])->save();
            } else {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $resolvedVariant?->id,
                    'variant_key' => $variantKey,
                    'quantity' => $targetQuantity,
                ]);
            }

            return $cart->refresh();
        });
    }

    /**
     * Update an existing cart line quantity owned by the given cart.
     *
     * @throws ValidationException
     */
    public function updateItemQuantity(Cart $cart, CartItem $item, int $quantity): void
    {
        if ($item->cart_id !== $cart->id) {
            abort(403);
        }

        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        DB::transaction(function () use ($item, $quantity): void {
            /** @var CartItem $lockedItem */
            $lockedItem = CartItem::query()
                ->with(['product.category', 'product.store', 'variant'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $product = $lockedItem->product;
            if (! $product->isStorefrontVisible()) {
                throw ValidationException::withMessages([
                    'quantity' => "{$product->name} is no longer active.",
                ]);
            }

            $availableStock = $lockedItem->variant !== null
                ? max(0, (int) $lockedItem->variant->stock_quantity)
                : max(0, (int) $product->stock_quantity);

            if ($quantity > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$availableStock} unit(s) available for {$product->name}.",
                ]);
            }

            $lockedItem->forceFill(['quantity' => $quantity])->save();
        });
    }

    public function removeItem(Cart $cart, CartItem $item): void
    {
        if ($item->cart_id !== $cart->id) {
            abort(403);
        }

        $item->delete();
    }

    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Merge guest session cart items into an authenticated customer's cart on sign-in.
     */
    public function mergeGuestCartIntoUser(User $user, ?string $sessionToken): void
    {
        $cleanToken = is_string($sessionToken) ? trim($sessionToken) : '';
        if ($cleanToken === '') {
            return;
        }

        DB::transaction(function () use ($user, $cleanToken): void {
            /** @var Cart|null $guestCart */
            $guestCart = Cart::query()
                ->with(['items.product', 'items.variant'])
                ->whereNull('user_id')
                ->where('session_token', $cleanToken)
                ->lockForUpdate()
                ->first();

            if ($guestCart === null) {
                return;
            }

            if ($guestCart->items->isEmpty()) {
                $guestCart->delete();

                return;
            }

            $userCart = $this->resolveOrCreateCart($user, null);

            foreach ($guestCart->items as $guestItem) {
                $available = $guestItem->variant !== null
                    ? max(0, (int) $guestItem->variant->stock_quantity)
                    : max(0, (int) $guestItem->product->stock_quantity);

                if ($available <= 0) {
                    continue;
                }

                /** @var CartItem|null $existing */
                $existing = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->where('variant_key', $guestItem->variant_key)
                    ->lockForUpdate()
                    ->first();

                $mergedQuantity = min($available, ($existing->quantity ?? 0) + $guestItem->quantity);

                if ($existing !== null) {
                    $existing->forceFill(['quantity' => $mergedQuantity])->save();
                } else {
                    CartItem::query()->create([
                        'cart_id' => $userCart->id,
                        'product_id' => $guestItem->product_id,
                        'product_variant_id' => $guestItem->product_variant_id,
                        'variant_key' => $guestItem->variant_key,
                        'quantity' => $mergedQuantity,
                    ]);
                }
            }

            $guestCart->delete();
        });
    }

    /**
     * Return lightweight cart badge summary without creating DB rows on read requests.
     *
     * @return array{itemCount: int, subtotalFormatted: string|null, items: list<array<string, mixed>>}
     */
    public function summaryForRequest(Request $request): array
    {
        $cart = $this->findExistingCart(
            $request->user(),
            $request->hasSession() ? $this->sessionToken($request, false) : null,
        );

        if (! $cart instanceof Cart) {
            return [
                'itemCount' => 0,
                'subtotalFormatted' => null,
                'items' => [],
            ];
        }

        $presented = $this->presentCart($cart);

        return [
            'itemCount' => $presented['itemCount'],
            'subtotalFormatted' => $presented['itemCount'] > 0 ? $presented['subtotalFormatted'] : null,
            'items' => array_slice($presented['items'], 0, 5),
        ];
    }

    /**
     * Present the full server-verified cart with fresh catalog prices and stock limits.
     *
     * @return array{
     *     itemCount: int,
     *     storeCount: int,
     *     currency: string,
     *     subtotalCentavos: int,
     *     subtotalFormatted: string,
     *     shippingFormatted: string,
     *     totalCentavos: int,
     *     totalFormatted: string,
     *     hasStockIssues: bool,
     *     items: list<array<string, mixed>>,
     *     sellerGroups: list<array<string, mixed>>,
     * }
     */
    public function presentCart(?Cart $cart): array
    {
        if (! $cart instanceof Cart) {
            return [
                'itemCount' => 0,
                'storeCount' => 0,
                'currency' => 'PHP',
                'subtotalCentavos' => 0,
                'subtotalFormatted' => '₱0.00',
                'shippingFormatted' => '₱0.00',
                'totalCentavos' => 0,
                'totalFormatted' => '₱0.00',
                'hasStockIssues' => false,
                'items' => [],
                'sellerGroups' => [],
            ];
        }

        $cartItems = $cart->items()
            ->with([
                'product.category',
                'product.store',
                'product.images',
                'variant',
            ])
            ->get();

        $subtotalCentavos = 0;
        $itemCount = 0;
        $hasStockIssues = false;
        $items = [];
        /** @var array<string, array{
         *     storeId: int|null,
         *     storeName: string,
         *     storeSlug: string|null,
         *     storeUrl: string|null,
         *     isOfficialStore: bool,
         *     itemCount: int,
         *     subtotalCentavos: int,
         *     hasStockIssues: bool,
         *     items: list<array<string, mixed>>
         * }> $groupsMap */
        $groupsMap = [];
        $currency = $cart->currency !== '' ? $cart->currency : 'PHP';

        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->variant;
            $isActiveProduct = $product->isStorefrontVisible()
                && ($variant === null || $variant->is_active);

            $storeModel = $product->store instanceof Store ? $product->store : null;
            $isStoreActive = $storeModel instanceof Store && $storeModel->isActive();
            $storeName = $storeModel instanceof Store ? $storeModel->name : $product->seller_name;
            $storeUrl = $isStoreActive ? route('stores.show', ['store' => $storeModel->slug]) : null;
            $isOfficialStore = $storeModel instanceof Store ? $storeModel->is_official : true;

            $unitPriceStr = $variant !== null ? (string) $variant->price : (string) $product->base_price;
            $unitCentavos = MoneyFormatter::toCentavos($unitPriceStr);
            $availableStock = $isActiveProduct
                ? ($variant !== null ? max(0, (int) $variant->stock_quantity) : max(0, (int) $product->stock_quantity))
                : 0;

            $lineCentavos = $unitCentavos * $item->quantity;
            $lineAmountFormatted = MoneyFormatter::format(number_format($lineCentavos / 100, 2, '.', ''), $currency);

            $itemHasStockIssue = ! $isActiveProduct || $availableStock < $item->quantity;
            if ($itemHasStockIssue) {
                $hasStockIssues = true;
            }

            $itemCount += $item->quantity;
            $subtotalCentavos += $lineCentavos;

            /** @var ProductImage|null $matchedImage */
            $matchedImage = ($variant !== null ? $product->images->firstWhere('variant_id', $variant->id) : null)
                ?? $product->images->first();

            $availabilityNote = null;
            if (! $isActiveProduct) {
                $availabilityNote = $storeModel instanceof Store && ! $storeModel->isActive()
                    ? 'This store is temporarily unavailable.'
                    : 'Product is currently unavailable.';
            } elseif ($availableStock < $item->quantity) {
                $availabilityNote = "Only {$availableStock} unit(s) remaining.";
            }

            $presentedItem = [
                'id' => $item->id,
                'productId' => $product->id,
                'productVariantId' => $variant?->id,
                'productName' => $product->name,
                'productSlug' => $product->slug,
                'productUrl' => route('products.show', ['product' => $product->slug]),
                'variantTitle' => $variant?->title,
                'sku' => $variant->sku ?? $product->sku,
                'sellerName' => $storeName,
                'storeId' => $product->store_id,
                'storeSlug' => $isStoreActive ? $storeModel->slug : null,
                'storeUrl' => $storeUrl,
                'isOfficialStore' => $isOfficialStore,
                'unitPriceFormatted' => MoneyFormatter::format($unitPriceStr, $currency),
                'quantity' => $item->quantity,
                'maxQuantity' => $availableStock,
                'isAvailable' => ! $itemHasStockIssue,
                'availabilityNote' => $availabilityNote,
                'lineTotalFormatted' => $lineAmountFormatted,
                'imageUrl' => $matchedImage instanceof ProductImage ? $this->catalogPresenter->imageUrl($matchedImage) : null,
                'imageAlt' => $matchedImage instanceof ProductImage ? $matchedImage->alt_text : $product->name,
            ];

            $items[] = $presentedItem;

            $groupKey = $product->store_id !== null
                ? "store-{$product->store_id}"
                : 'name-'.mb_strtolower($storeName);

            if (! isset($groupsMap[$groupKey])) {
                $groupsMap[$groupKey] = [
                    'storeId' => $product->store_id,
                    'storeName' => $storeName,
                    'storeSlug' => $isStoreActive ? $storeModel->slug : null,
                    'storeUrl' => $storeUrl,
                    'isOfficialStore' => $isOfficialStore,
                    'itemCount' => 0,
                    'subtotalCentavos' => 0,
                    'hasStockIssues' => false,
                    'items' => [],
                ];
            }

            $groupsMap[$groupKey]['itemCount'] += $item->quantity;
            $groupsMap[$groupKey]['subtotalCentavos'] += $lineCentavos;
            if ($itemHasStockIssue) {
                $groupsMap[$groupKey]['hasStockIssues'] = true;
            }
            $groupsMap[$groupKey]['items'][] = $presentedItem;
        }

        $sellerGroups = array_values(array_map(
            static fn (array $group): array => [
                ...$group,
                'subtotalFormatted' => MoneyFormatter::format(
                    number_format($group['subtotalCentavos'] / 100, 2, '.', ''),
                    $currency,
                ),
            ],
            $groupsMap,
        ));

        $formattedSubtotal = MoneyFormatter::format(number_format($subtotalCentavos / 100, 2, '.', ''), $currency);

        return [
            'itemCount' => $itemCount,
            'storeCount' => count($sellerGroups),
            'currency' => $currency,
            'subtotalCentavos' => $subtotalCentavos,
            'subtotalFormatted' => $formattedSubtotal,
            'shippingFormatted' => MoneyFormatter::format('0.00', $currency),
            'totalCentavos' => $subtotalCentavos,
            'totalFormatted' => $formattedSubtotal,
            'hasStockIssues' => $hasStockIssues,
            'items' => $items,
            'sellerGroups' => $sellerGroups,
        ];
    }
}
