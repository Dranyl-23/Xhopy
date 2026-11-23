<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronRight,
    Heart,
    ImageOff,
    ShieldCheck,
    ShoppingCart,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { CartPreviewDialog, type CartSummary } from '@modules/cart';
import PriceDisplay from '@modules/catalog/components/PriceDisplay.vue';
import ProductCard from '@modules/catalog/components/ProductCard.vue';
import QuantitySelector from '@modules/catalog/components/QuantitySelector.vue';
import RatingDisplay from '@modules/catalog/components/RatingDisplay.vue';
import { StorefrontLayout } from '@modules/storefront';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Separator } from '@shared/components/ui/separator';
import { home, login } from '@/routes';
import { index as cartIndex } from '@/routes/cart';
import { store as cartItemsStore } from '@/routes/cart/items';
import { index as catalogIndex } from '@/routes/catalog';
import { toggle as wishlistToggle } from '@/routes/wishlist';
import type {
    ProductDetailProps,
    ProductSummary,
    ProductVariantItem,
} from '@modules/catalog/types';

const props = defineProps<ProductDetailProps>();
const page = usePage();
const isWishlisted = computed(() =>
    (page.props.wishlistProductIds ?? []).includes(Number(props.product.id)),
);

function handleToggleWishlist(): void {
    if (!page.props.auth?.user) {
        router.visit(login.url());
        return;
    }

    router.post(
        wishlistToggle.url(Number(props.product.id)),
        {},
        { preserveScroll: true },
    );
}

const defaultVariant = computed<ProductVariantItem | null>(
    () =>
        props.product.variants.find((v) => v.isDefault) ??
        props.product.variants[0] ??
        null,
);

const selectedVariantId = ref<number | null>(defaultVariant.value?.id ?? null);
const selectedImageIndex = ref(0);
const quantity = ref(1);
const localCartCount = ref(props.cartSummary.itemCount);
const cartDialogOpen = ref(false);
const lastAddedName = ref<string | null>(null);
const lastAddedPrice = ref<string | null>(null);
const justAdded = ref(false);
const cartError = ref<string | null>(null);

const selectedVariant = computed<ProductVariantItem | null>(() => {
    if (selectedVariantId.value === null) {
        return defaultVariant.value;
    }

    return (
        props.product.variants.find((v) => v.id === selectedVariantId.value) ??
        defaultVariant.value
    );
});

const activeSku = computed(
    () => selectedVariant.value?.sku ?? props.product.sku ?? '',
);
const activePriceFormatted = computed(
    () => selectedVariant.value?.priceFormatted ?? props.product.priceFormatted,
);
const activeOriginalPriceFormatted = computed(() =>
    selectedVariant.value
        ? selectedVariant.value.originalPriceFormatted
        : props.product.originalPriceFormatted,
);
const activeDiscountLabel = computed(() =>
    selectedVariant.value
        ? selectedVariant.value.discountLabel
        : props.product.discountLabel,
);
const activeInStock = computed(() =>
    selectedVariant.value
        ? selectedVariant.value.inStock
        : props.product.inStock,
);
const activeStockLabel = computed(() =>
    selectedVariant.value
        ? selectedVariant.value.stockLabel
        : props.product.stockLabel,
);
const activeStockQuantity = computed(() =>
    selectedVariant.value
        ? selectedVariant.value.stockQuantity
        : props.product.stockQuantity,
);

const activeImage = computed(
    () => props.product.images[selectedImageIndex.value] ?? null,
);

const specificationEntries = computed(() =>
    Object.entries(props.product.specifications ?? {}).filter(
        ([k, v]) => k.trim() !== '' && v.trim() !== '',
    ),
);

const effectiveCart = computed<CartSummary>(() => ({
    itemCount: localCartCount.value,
    subtotalFormatted: props.cartSummary.subtotalFormatted,
    items: props.cartSummary.items,
}));

watch(
    () => props.cartSummary.itemCount,
    (count) => {
        localCartCount.value = count;
    },
);

watch(selectedVariantId, (variantId) => {
    quantity.value = 1;
    if (variantId === null) {
        return;
    }

    const matchingImgIdx = props.product.images.findIndex(
        (img) => img.variantId === variantId,
    );
    if (matchingImgIdx >= 0) {
        selectedImageIndex.value = matchingImgIdx;
    }
});

function selectVariant(variant: ProductVariantItem): void {
    selectedVariantId.value = variant.id;
}

function handleAddCurrentProduct(): void {
    if (!activeInStock.value) {
        return;
    }

    cartError.value = null;

    const qty = Math.max(
        1,
        Math.min(quantity.value, Math.max(1, activeStockQuantity.value)),
    );
    const addedLabel = selectedVariant.value
        ? `${props.product.name} (${selectedVariant.value.title})`
        : props.product.name;

    if (typeof props.product.id !== 'number') {
        localCartCount.value += qty;
        lastAddedName.value = addedLabel;
        lastAddedPrice.value = activePriceFormatted.value;
        justAdded.value = true;
        window.setTimeout(() => {
            justAdded.value = false;
        }, 1500);
        return;
    }

    router.post(
        cartItemsStore.url(),
        {
            product_id: props.product.id,
            product_variant_id: selectedVariant.value?.id ?? null,
            quantity: qty,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                lastAddedName.value = addedLabel;
                lastAddedPrice.value = activePriceFormatted.value;
                justAdded.value = true;
                window.setTimeout(() => {
                    justAdded.value = false;
                }, 1500);
            },
            onError: (errors) => {
                cartError.value =
                    errors.quantity ??
                    errors.product_id ??
                    'Unable to add this item to your cart.';
            },
        },
    );
}

function handleAddRelated(related: ProductSummary): void {
    cartError.value = null;

    if (typeof related.id !== 'number') {
        localCartCount.value += 1;
        lastAddedName.value = related.name;
        lastAddedPrice.value = related.priceFormatted;
        return;
    }

    router.post(
        cartItemsStore.url(),
        {
            product_id: related.id,
            quantity: 1,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                lastAddedName.value = related.name;
                lastAddedPrice.value = related.priceFormatted;
            },
        },
    );
}
</script>

<template>
    <Head :title="`${product.name} — XHopy`" />

    <StorefrontLayout
        :categories="categories"
        :cart="effectiveCart"
        :selected-category="product.categorySlug ?? null"
        @search="
            router.get(catalogIndex.url({ query: { q: $event || undefined } }))
        "
        @select-category="
            router.get(
                catalogIndex.url({ query: { category: $event || undefined } }),
            )
        "
        @open-cart="cartDialogOpen = true"
    >
        <div class="mx-auto max-w-7xl space-y-12 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <nav
                aria-label="Breadcrumb"
                class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Link :href="home()" class="hover:text-foreground">Home</Link>
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <Link :href="catalogIndex()" class="hover:text-foreground">
                    Catalog
                </Link>
                <template v-if="product.categoryName && product.categorySlug">
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                    <Link
                        :href="
                            catalogIndex({
                                query: { category: product.categorySlug },
                            })
                        "
                        class="hover:text-foreground"
                    >
                        {{ product.categoryName }}
                    </Link>
                </template>
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <span class="truncate font-medium text-foreground">{{
                    product.name
                }}</span>
            </nav>

            <!-- Product Showcase -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-12">
                <!-- Left: Media Gallery -->
                <div class="space-y-4 lg:col-span-6">
                    <div
                        class="relative aspect-square w-full overflow-hidden rounded-2xl border border-border bg-secondary/50"
                    >
                        <img
                            v-if="activeImage"
                            :src="activeImage.url"
                            :alt="activeImage.alt"
                            width="640"
                            height="640"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full flex-col items-center justify-center gap-3 p-8 text-center text-muted-foreground"
                        >
                            <ImageOff
                                class="size-10 opacity-50"
                                aria-hidden="true"
                            />
                            <p class="text-sm font-medium">
                                {{ product.name }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="product.images.length > 1"
                        class="grid grid-cols-5 gap-3"
                    >
                        <button
                            v-for="(image, index) in product.images"
                            :key="image.id"
                            type="button"
                            :aria-label="`View image ${index + 1}: ${image.alt}`"
                            :aria-pressed="selectedImageIndex === index"
                            :class="[
                                'aspect-square overflow-hidden rounded-xl border bg-secondary/40 transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                selectedImageIndex === index
                                    ? 'border-primary ring-2 ring-primary'
                                    : 'border-border hover:border-primary/50',
                            ]"
                            @click="selectedImageIndex = index"
                        >
                            <img
                                :src="image.url"
                                :alt="image.alt"
                                class="size-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <!-- Right: Information, Variant Picker & Actions -->
                <div class="space-y-6 lg:col-span-6">
                    <div class="space-y-2.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="product.categoryName"
                                variant="secondary"
                            >
                                {{ product.categoryName }}
                            </Badge>
                            <Badge v-if="product.brandName" variant="outline">
                                {{ product.brandName }}
                            </Badge>
                            <Badge
                                :variant="
                                    activeInStock ? 'secondary' : 'outline'
                                "
                                :class="
                                    activeInStock
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ activeStockLabel }}
                            </Badge>
                        </div>

                        <h1
                            class="text-2xl leading-tight font-extrabold tracking-tight text-foreground sm:text-3xl"
                        >
                            {{ product.name }}
                        </h1>

                        <div
                            class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
                        >
                            <span v-if="activeSku">
                                SKU:
                                <strong class="font-mono text-foreground">{{
                                    activeSku
                                }}</strong>
                            </span>
                            <span
                                v-if="product.sellerName"
                                class="inline-flex flex-wrap items-center gap-1.5"
                            >
                                <span>Sold by</span>
                                <Link
                                    v-if="product.storeUrl"
                                    :href="product.storeUrl"
                                    class="font-semibold text-foreground underline-offset-2 transition-colors hover:text-primary hover:underline"
                                >
                                    {{ product.sellerName }}
                                </Link>
                                <strong v-else class="text-foreground">{{
                                    product.sellerName
                                }}</strong>
                                <span
                                    v-if="product.isOfficialStore"
                                    class="inline-flex items-center rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-primary uppercase"
                                >
                                    Official Store
                                </span>
                            </span>
                            <RatingDisplay
                                :rating-average="product.ratingAverage"
                                :rating-count="product.ratingCount"
                            />
                        </div>
                    </div>

                    <PriceDisplay
                        size="lg"
                        :price-formatted="activePriceFormatted"
                        :original-price-formatted="activeOriginalPriceFormatted"
                        :discount-label="activeDiscountLabel"
                    />

                    <p
                        v-if="product.shortDescription"
                        class="text-sm leading-relaxed text-muted-foreground sm:text-base"
                    >
                        {{ product.shortDescription }}
                    </p>

                    <Separator />

                    <!-- Variant Selector -->
                    <div v-if="product.variants.length > 0" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-semibold tracking-wider text-foreground uppercase"
                            >
                                Available Options
                            </span>
                            <span
                                v-if="selectedVariant"
                                class="text-xs text-muted-foreground"
                            >
                                Selected:
                                <strong class="text-foreground">{{
                                    selectedVariant.title
                                }}</strong>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <button
                                v-for="variant in product.variants"
                                :key="variant.id"
                                type="button"
                                :aria-pressed="
                                    selectedVariant?.id === variant.id
                                "
                                :class="[
                                    'flex flex-col rounded-xl border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    selectedVariant?.id === variant.id
                                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                        : 'border-border bg-card hover:border-primary/40',
                                    !variant.inStock && 'opacity-70',
                                ]"
                                @click="selectVariant(variant)"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{ variant.title }}
                                    </span>
                                    <span
                                        class="text-xs font-bold text-primary tabular-nums"
                                    >
                                        {{ variant.priceFormatted }}
                                    </span>
                                </div>
                                <div
                                    class="mt-1 flex items-center justify-between text-[11px] text-muted-foreground"
                                >
                                    <span class="font-mono">{{
                                        variant.sku
                                    }}</span>
                                    <span>{{ variant.stockLabel }}</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Quantity + Add to Cart -->
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <QuantitySelector
                            v-model="quantity"
                            :min="1"
                            :max="Math.max(1, activeStockQuantity)"
                            :disabled="!activeInStock"
                        />

                        <Button
                            type="button"
                            size="lg"
                            class="h-11 flex-1 gap-2 text-sm font-semibold"
                            :disabled="!activeInStock"
                            @click="handleAddCurrentProduct"
                        >
                            <Check
                                v-if="justAdded"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <ShoppingCart
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            <span>
                                {{
                                    !activeInStock
                                        ? 'Currently Out of Stock'
                                        : justAdded
                                          ? 'Added to Cart'
                                          : 'Add to Cart'
                                }}
                            </span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            class="h-11 gap-2"
                            :aria-pressed="isWishlisted"
                            @click="handleToggleWishlist"
                        >
                            <Heart
                                :class="[
                                    'size-4',
                                    isWishlisted
                                        ? 'fill-primary text-primary'
                                        : '',
                                ]"
                                aria-hidden="true"
                            />
                            <span>{{ isWishlisted ? 'Saved' : 'Save' }}</span>
                        </Button>

                        <Button
                            v-if="effectiveCart.itemCount > 0"
                            as-child
                            variant="outline"
                            size="lg"
                            class="h-11"
                        >
                            <Link :href="cartIndex()">View Cart</Link>
                        </Button>
                    </div>

                    <p
                        v-if="cartError"
                        role="alert"
                        class="text-sm font-medium text-destructive"
                    >
                        {{ cartError }}
                    </p>

                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-secondary/40 px-4 py-3 text-xs text-muted-foreground"
                    >
                        <ShieldCheck
                            class="size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            Displayed pricing and availability come directly
                            from XHopy's server catalog and are re-verified
                            prior to checkout.
                        </span>
                    </div>

                    <!-- Full Description & Specifications -->
                    <div v-if="product.description" class="space-y-2 pt-2">
                        <h2
                            class="text-sm font-bold tracking-wider text-foreground uppercase"
                        >
                            Product Overview
                        </h2>
                        <p
                            class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                        >
                            {{ product.description }}
                        </p>
                    </div>

                    <div
                        v-if="specificationEntries.length > 0"
                        class="space-y-3 pt-2"
                    >
                        <h2
                            class="text-sm font-bold tracking-wider text-foreground uppercase"
                        >
                            Specifications
                        </h2>
                        <dl
                            class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card text-sm"
                        >
                            <div
                                v-for="[
                                    specKey,
                                    specVal,
                                ] in specificationEntries"
                                :key="specKey"
                                class="grid grid-cols-3 gap-4 px-4 py-2.5"
                            >
                                <dt class="font-medium text-muted-foreground">
                                    {{ specKey }}
                                </dt>
                                <dd
                                    class="col-span-2 font-medium text-foreground"
                                >
                                    {{ specVal }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <section
                v-if="relatedProducts.length > 0"
                aria-labelledby="related-products-heading"
                class="space-y-5 border-t border-border pt-10"
            >
                <div class="flex items-center justify-between">
                    <h2
                        id="related-products-heading"
                        class="text-xl font-bold tracking-tight text-foreground"
                    >
                        Related in {{ product.categoryName ?? 'Catalog' }}
                    </h2>
                    <Link
                        v-if="product.categorySlug"
                        :href="
                            catalogIndex({
                                query: { category: product.categorySlug },
                            })
                        "
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        View category
                    </Link>
                </div>

                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <ProductCard
                        v-for="item in relatedProducts"
                        :key="item.id"
                        :product="item"
                        @add-to-cart="handleAddRelated"
                    />
                </div>
            </section>
        </div>

        <CartPreviewDialog
            v-model:open="cartDialogOpen"
            :cart="effectiveCart"
            :last-added-name="lastAddedName"
            :last-added-price-formatted="lastAddedPrice"
        />
    </StorefrontLayout>
</template>
