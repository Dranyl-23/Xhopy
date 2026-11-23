<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Package,
    ShieldCheck,
    ShoppingBag,
    Store,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import {
    CartPreviewDialog,
    type CartItemRow,
    type CartPageProps,
} from '@modules/cart';
import { QuantitySelector } from '@modules/catalog';
import { StorefrontLayout } from '@modules/storefront';
import EmptyState from '@shared/components/EmptyState.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Separator } from '@shared/components/ui/separator';
import { index as catalogIndex } from '@/routes/catalog';
import { clear as cartClear } from '@/routes/cart';
import {
    destroy as cartItemDestroy,
    update as cartItemUpdate,
} from '@/routes/cart/items';
import { index as checkoutIndex } from '@/routes/checkout';

defineProps<CartPageProps>();

const cartDialogOpen = ref(false);
const processingItemId = ref<number | null>(null);
const cartActionError = ref<string | null>(null);

function handleSearch(query: string): void {
    router.get(
        catalogIndex.url({
            query: {
                q: query || undefined,
            },
        }),
    );
}

function handleSelectCategory(slug: string | null): void {
    router.get(
        catalogIndex.url({
            query: {
                category: slug || undefined,
            },
        }),
    );
}

function updateItemQuantity(item: CartItemRow, nextQuantity: number): void {
    cartActionError.value = null;
    processingItemId.value = item.id;

    router.patch(
        cartItemUpdate.url(item.id),
        {
            quantity: nextQuantity,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                cartActionError.value =
                    errors.quantity ?? 'Unable to update item quantity.';
            },
            onFinish: () => {
                processingItemId.value = null;
            },
        },
    );
}

function removeItem(item: CartItemRow): void {
    cartActionError.value = null;
    processingItemId.value = item.id;

    router.delete(cartItemDestroy.url(item.id), {
        preserveScroll: true,
        onFinish: () => {
            processingItemId.value = null;
        },
    });
}

function clearCart(): void {
    cartActionError.value = null;
    router.delete(cartClear.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Shopping Cart — XHopy" />

    <StorefrontLayout
        :categories="categories"
        :cart="cartSummary"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="cartDialogOpen = true"
    >
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="flex flex-col justify-between gap-4 border-b border-border pb-6 sm:flex-row sm:items-end"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <h1
                            class="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                        >
                            Shopping Cart
                        </h1>
                        <Badge variant="secondary" class="text-xs">
                            {{ cart.itemCount }}
                            {{ cart.itemCount === 1 ? 'item' : 'items' }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Quantities and prices are verified against live server
                        catalog stock before payment.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button as-child variant="outline" class="h-11">
                        <Link :href="catalogIndex()">Continue Shopping</Link>
                    </Button>
                    <Button
                        v-if="cart.items.length > 0"
                        type="button"
                        variant="ghost"
                        class="h-11 text-destructive hover:text-destructive"
                        @click="clearCart"
                    >
                        Clear Cart
                    </Button>
                </div>
            </div>

            <Alert
                v-if="cartActionError"
                variant="destructive"
                class="mt-6 rounded-xl"
            >
                <AlertTriangle class="size-4" aria-hidden="true" />
                <AlertTitle>Cart Update Failed</AlertTitle>
                <AlertDescription>{{ cartActionError }}</AlertDescription>
            </Alert>

            <Alert
                v-if="cart.hasStockIssues && cart.items.length > 0"
                variant="destructive"
                class="mt-6 rounded-xl"
            >
                <AlertTriangle class="size-4" aria-hidden="true" />
                <AlertTitle>Action Required Before Checkout</AlertTitle>
                <AlertDescription>
                    One or more items in your cart exceed current available
                    stock or are no longer active. Adjust quantities or remove
                    them to continue.
                </AlertDescription>
            </Alert>

            <div
                v-if="cart.items.length > 0"
                class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-12"
            >
                <!-- Line Items Grouped by Seller Store -->
                <div class="space-y-5 lg:col-span-8">
                    <section
                        v-for="group in cart.sellerGroups"
                        :key="`${group.storeId ?? 'store'}-${group.storeName}`"
                        :aria-label="`Items from ${group.storeName}`"
                        class="overflow-hidden rounded-2xl border border-border bg-card"
                    >
                        <header
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-secondary/35 px-4 py-3 sm:px-5"
                        >
                            <div
                                class="flex min-w-0 flex-wrap items-center gap-2"
                            >
                                <Store
                                    class="size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <Link
                                    v-if="group.storeUrl"
                                    :href="group.storeUrl"
                                    class="truncate text-sm font-bold text-foreground transition-colors hover:text-primary"
                                >
                                    {{ group.storeName }}
                                </Link>
                                <span
                                    v-else
                                    class="truncate text-sm font-bold text-foreground"
                                >
                                    {{ group.storeName }}
                                </span>
                                <Badge
                                    v-if="group.isOfficialStore"
                                    variant="default"
                                    class="px-1.5 py-0.5 text-[10px] tracking-wide uppercase"
                                >
                                    Official
                                </Badge>
                                <Badge
                                    v-else
                                    variant="secondary"
                                    class="px-1.5 py-0.5 text-[10px] tracking-wide uppercase"
                                >
                                    Verified Store
                                </Badge>
                            </div>

                            <div
                                class="flex items-center gap-3 text-xs text-muted-foreground"
                            >
                                <span>
                                    {{ group.itemCount }}
                                    {{
                                        group.itemCount === 1 ? 'item' : 'items'
                                    }}
                                </span>
                                <span
                                    class="font-semibold text-foreground tabular-nums"
                                >
                                    Store Subtotal:
                                    {{ group.subtotalFormatted }}
                                </span>
                            </div>
                        </header>

                        <div class="divide-y divide-border">
                            <article
                                v-for="item in group.items"
                                :key="item.id"
                                class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-start gap-4"
                                >
                                    <Link
                                        :href="item.productUrl"
                                        class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-secondary/40"
                                    >
                                        <img
                                            v-if="item.imageUrl"
                                            :src="item.imageUrl"
                                            :alt="item.imageAlt"
                                            class="size-full object-cover"
                                        />
                                        <Package
                                            v-else
                                            class="size-6 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </Link>

                                    <div class="min-w-0 flex-1 space-y-1">
                                        <Link
                                            :href="item.productUrl"
                                            class="block truncate text-base font-semibold text-foreground hover:text-primary"
                                        >
                                            {{ item.productName }}
                                        </Link>

                                        <div
                                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
                                        >
                                            <span v-if="item.variantTitle"
                                                >Variant:
                                                {{ item.variantTitle }}</span
                                            >
                                            <span>SKU: {{ item.sku }}</span>
                                        </div>

                                        <p
                                            class="text-sm font-medium text-foreground tabular-nums"
                                        >
                                            {{ item.unitPriceFormatted }} each
                                        </p>

                                        <p
                                            v-if="
                                                !item.isAvailable &&
                                                item.availabilityNote
                                            "
                                            class="text-xs font-medium text-destructive"
                                        >
                                            {{ item.availabilityNote }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border pt-3 sm:justify-end sm:border-t-0 sm:pt-0"
                                >
                                    <QuantitySelector
                                        :model-value="item.quantity"
                                        :min="1"
                                        :max="Math.max(1, item.maxQuantity)"
                                        :disabled="
                                            processingItemId === item.id ||
                                            item.maxQuantity === 0
                                        "
                                        @update:model-value="
                                            updateItemQuantity(item, $event)
                                        "
                                    />

                                    <div class="min-w-24 text-right">
                                        <p
                                            class="text-base font-bold text-foreground tabular-nums"
                                        >
                                            {{ item.lineTotalFormatted }}
                                        </p>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-11 text-muted-foreground hover:text-destructive"
                                        :disabled="processingItemId === item.id"
                                        :aria-label="`Remove ${item.productName}`"
                                        @click="removeItem(item)"
                                    >
                                        <Trash2
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </div>
                            </article>
                        </div>
                    </section>
                </div>

                <!-- Summary -->
                <aside class="lg:col-span-4">
                    <div
                        class="space-y-4 rounded-2xl border border-border bg-card p-6 lg:sticky lg:top-24"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-bold text-foreground">
                                Order Summary
                            </h2>
                            <Badge variant="outline" class="text-[11px]">
                                {{ cart.storeCount }}
                                {{ cart.storeCount === 1 ? 'store' : 'stores' }}
                            </Badge>
                        </div>

                        <div
                            v-if="cart.sellerGroups.length > 1"
                            class="space-y-1.5 rounded-xl border border-border/70 bg-secondary/30 p-3 text-xs"
                        >
                            <p
                                class="font-semibold text-muted-foreground uppercase"
                            >
                                Store Breakdown
                            </p>
                            <div
                                v-for="group in cart.sellerGroups"
                                :key="`summary-${group.storeId ?? 'store'}-${group.storeName}`"
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="truncate text-foreground">{{
                                    group.storeName
                                }}</span>
                                <span
                                    class="shrink-0 font-semibold text-foreground tabular-nums"
                                    >{{ group.subtotalFormatted }}</span
                                >
                            </div>
                        </div>

                        <div class="space-y-2.5 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Subtotal ({{ cart.itemCount }}
                                    {{
                                        cart.itemCount === 1 ? 'item' : 'items'
                                    }})</span
                                >
                                <span
                                    class="font-medium text-foreground tabular-nums"
                                    >{{ cart.subtotalFormatted }}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground"
                                    >Standard Shipping</span
                                >
                                <span
                                    class="font-medium text-foreground tabular-nums"
                                    >{{ cart.shippingFormatted }}</span
                                >
                            </div>
                        </div>

                        <Separator />

                        <div class="flex items-center justify-between">
                            <span class="text-base font-bold text-foreground"
                                >Total</span
                            >
                            <span
                                class="text-xl font-extrabold text-primary tabular-nums"
                                >{{ cart.totalFormatted }}</span
                            >
                        </div>

                        <Button
                            v-if="!cart.hasStockIssues"
                            as-child
                            size="lg"
                            class="h-11 w-full gap-2 text-sm font-semibold"
                        >
                            <Link :href="checkoutIndex()">
                                <span>Proceed to Checkout</span>
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>

                        <Button
                            v-else
                            type="button"
                            size="lg"
                            disabled
                            class="h-11 w-full text-sm font-semibold"
                        >
                            Resolve Stock Issues to Continue
                        </Button>

                        <div
                            class="flex items-start gap-2 rounded-xl border border-border bg-secondary/40 px-3.5 py-2.5 text-xs text-muted-foreground"
                        >
                            <ShieldCheck
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span>
                                Stock is atomically reserved upon checkout and
                                payments are processed securely via PayMongo.
                            </span>
                        </div>
                    </div>
                </aside>
            </div>

            <EmptyState
                v-else
                class="mt-8"
                :icon="ShoppingBag"
                title="Your shopping cart is empty"
                description="Add products from the XHopy catalog to build your order."
                action-label="Browse Catalog"
                @action="router.get(catalogIndex.url())"
            />
        </div>

        <CartPreviewDialog v-model:open="cartDialogOpen" :cart="cartSummary" />
    </StorefrontLayout>
</template>
