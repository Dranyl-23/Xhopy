<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CreditCard,
    MapPin,
    ShieldCheck,
    Store,
} from '@lucide/vue';
import { computed } from 'vue';
import { StorefrontLayout } from '@modules/storefront';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Label } from '@shared/components/ui/label';
import { Separator } from '@shared/components/ui/separator';
import { index as addressesIndex } from '@/routes/addresses';
import { index as cartIndex } from '@/routes/cart';
import { index as catalogIndex } from '@/routes/catalog';
import { store as checkoutStore } from '@/routes/checkout';
import type { CheckoutIndexProps } from '@modules/checkout/types';

const props = defineProps<CheckoutIndexProps>();

const form = useForm<{
    address_id: number | null;
    idempotency_key: string;
    notes: string;
}>({
    address_id: props.defaultAddressId,
    idempotency_key: props.idempotencyKey,
    notes: '',
});

const firstError = computed<string | null>(() => {
    const entries = Object.values(form.errors).filter(
        (value): value is string => typeof value === 'string' && value !== '',
    );

    return entries[0] ?? null;
});

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

function submitCheckout(): void {
    if (form.address_id === null || props.cart.hasStockIssues) {
        return;
    }

    form.post(checkoutStore.url());
}
</script>

<template>
    <Head title="Checkout — XHopy" />

    <StorefrontLayout
        :cart="cartSummary"
        @search="handleSearch"
        @select-category="handleSelectCategory"
        @open-cart="router.get(cartIndex.url())"
    >
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 space-y-2">
                <Link
                    :href="cartIndex()"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-primary"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    <span>Back to Cart</span>
                </Link>
                <h1
                    class="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                >
                    Secure Checkout
                </h1>
                <p class="text-sm text-muted-foreground">
                    Select a delivery address and confirm your order. You will
                    be redirected to PayMongo Checkout to complete payment.
                </p>
            </div>

            <Alert
                v-if="firstError"
                variant="destructive"
                class="mb-6 rounded-xl"
            >
                <AlertTriangle class="size-4" aria-hidden="true" />
                <AlertTitle>Unable to Place Order</AlertTitle>
                <AlertDescription>{{ firstError }}</AlertDescription>
            </Alert>

            <Alert
                v-if="cart.hasStockIssues"
                variant="destructive"
                class="mb-6 rounded-xl"
            >
                <AlertTriangle class="size-4" aria-hidden="true" />
                <AlertTitle>Cart Stock Changed</AlertTitle>
                <AlertDescription>
                    Some items in your cart no longer have sufficient stock.
                    Return to your cart to adjust quantities.
                </AlertDescription>
            </Alert>

            <form
                class="grid grid-cols-1 gap-8 lg:grid-cols-12"
                @submit.prevent="submitCheckout"
            >
                <!-- Left Column: Shipping Address & Notes -->
                <div class="space-y-6 lg:col-span-7">
                    <Card>
                        <CardHeader
                            class="flex flex-row items-center justify-between gap-4"
                        >
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <MapPin
                                        class="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span>Delivery Address</span>
                                </CardTitle>
                                <CardDescription>
                                    Saved shipping addresses from your verified
                                    customer account.
                                </CardDescription>
                            </div>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="h-10 shrink-0"
                            >
                                <Link :href="addressesIndex()"
                                    >Manage Addresses</Link
                                >
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="addresses.length > 0"
                                role="radiogroup"
                                aria-label="Select shipping address"
                                class="space-y-3"
                            >
                                <label
                                    v-for="address in addresses"
                                    :key="address.id"
                                    :class="[
                                        'flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-colors',
                                        form.address_id === address.id
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                            : 'border-border hover:border-primary/50',
                                    ]"
                                >
                                    <input
                                        v-model="form.address_id"
                                        type="radio"
                                        name="address_id"
                                        :value="address.id"
                                        class="mt-1 size-4 accent-primary"
                                    />
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-semibold text-foreground"
                                            >
                                                {{ address.label }}
                                            </span>
                                            <Badge
                                                v-if="
                                                    address.is_default_shipping
                                                "
                                                variant="secondary"
                                                class="text-xs"
                                            >
                                                Default
                                            </Badge>
                                        </div>
                                        <p class="text-sm text-foreground">
                                            {{ address.recipient_name }} ·
                                            {{ address.phone }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ address.formatted_line }}
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <div
                                v-else
                                class="space-y-3 rounded-xl border border-dashed border-border p-5 text-sm text-muted-foreground"
                            >
                                <p>
                                    You have no saved shipping addresses yet.
                                    Add one to proceed with checkout.
                                </p>
                                <Button as-child size="sm" class="h-10">
                                    <Link :href="addressesIndex()">
                                        Add Shipping Address
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">
                                Order Notes (Optional)
                            </CardTitle>
                            <CardDescription>
                                Delivery instructions or landmark details for
                                the courier.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <Label for="checkout-notes" class="sr-only"
                                >Order Notes</Label
                            >
                            <textarea
                                id="checkout-notes"
                                v-model="form.notes"
                                rows="3"
                                maxlength="500"
                                placeholder="e.g., Leave package with lobby concierge if unavailable."
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column: Server-Verified Order Summary -->
                <aside class="lg:col-span-5">
                    <Card class="lg:sticky lg:top-24">
                        <CardHeader>
                            <CardTitle>Order Summary</CardTitle>
                            <CardDescription>
                                Prices and totals calculated on the server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="cart.sellerGroups.length > 0"
                                class="space-y-4"
                            >
                                <div
                                    v-for="group in cart.sellerGroups"
                                    :key="`${group.storeId ?? 'store'}-${group.storeName}`"
                                    class="rounded-xl border border-border/80 bg-secondary/20 p-3.5"
                                >
                                    <div
                                        class="mb-2 flex items-center justify-between gap-2 border-b border-border/60 pb-2 text-xs"
                                    >
                                        <span
                                            class="flex min-w-0 items-center gap-1.5 font-semibold text-foreground"
                                        >
                                            <Store
                                                class="size-3.5 shrink-0 text-primary"
                                                aria-hidden="true"
                                            />
                                            <span class="truncate">{{
                                                group.storeName
                                            }}</span>
                                        </span>
                                        <span
                                            class="shrink-0 font-semibold text-foreground tabular-nums"
                                        >
                                            {{ group.subtotalFormatted }}
                                        </span>
                                    </div>
                                    <ul
                                        class="divide-y divide-border/60 text-sm"
                                    >
                                        <li
                                            v-for="item in group.items"
                                            :key="item.id"
                                            class="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate font-medium text-foreground"
                                                >
                                                    {{ item.productName }}
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    <span
                                                        v-if="item.variantTitle"
                                                        >{{
                                                            item.variantTitle
                                                        }}
                                                        · </span
                                                    >Qty {{ item.quantity }}
                                                </p>
                                            </div>
                                            <span
                                                class="shrink-0 font-semibold text-foreground tabular-nums"
                                            >
                                                {{ item.lineTotalFormatted }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <ul v-else class="divide-y divide-border text-sm">
                                <li
                                    v-for="item in cart.items"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-3 py-2.5"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="truncate font-medium text-foreground"
                                        >
                                            {{ item.productName }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            <span v-if="item.variantTitle"
                                                >{{
                                                    item.variantTitle
                                                }}
                                                · </span
                                            >Qty {{ item.quantity }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 font-semibold text-foreground tabular-nums"
                                    >
                                        {{ item.lineTotalFormatted }}
                                    </span>
                                </li>
                            </ul>

                            <Separator />

                            <div class="space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground"
                                        >Subtotal</span
                                    >
                                    <span
                                        class="font-medium text-foreground tabular-nums"
                                        >{{ cart.subtotalFormatted }}</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground"
                                        >Shipping</span
                                    >
                                    <span
                                        class="font-medium text-foreground tabular-nums"
                                        >{{ cart.shippingFormatted }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between pt-2 text-base font-bold text-foreground"
                                >
                                    <span>Total Due</span>
                                    <span
                                        class="text-xl font-extrabold text-primary tabular-nums"
                                        >{{ cart.totalFormatted }}</span
                                    >
                                </div>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                class="h-11 w-full gap-2 text-sm font-semibold"
                                :disabled="
                                    form.processing ||
                                    form.address_id === null ||
                                    cart.hasStockIssues
                                "
                            >
                                <CreditCard class="size-4" aria-hidden="true" />
                                <span>
                                    {{
                                        form.processing
                                            ? 'Preparing Payment...'
                                            : 'Place Order & Pay with PayMongo'
                                    }}
                                </span>
                            </Button>

                            <div
                                class="flex items-start gap-2 rounded-xl border border-border bg-secondary/40 px-3.5 py-2.5 text-xs text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                <span>
                                    Order status only becomes Paid after
                                    verified PayMongo webhook or server session
                                    reconciliation.
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </form>
        </div>
    </StorefrontLayout>
</template>
