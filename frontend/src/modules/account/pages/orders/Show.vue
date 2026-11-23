<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CreditCard,
    History,
    MapPin,
    Package,
    Store,
    Truck,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Separator } from '@shared/components/ui/separator';
import { dashboard } from '@/routes';
import {
    cancel as orderCancel,
    index as ordersIndex,
    retryPayment as orderRetryPayment,
    show as orderShow,
} from '@/routes/orders';
import type { OrderDetailRecord } from '@modules/account/types';

const props = defineProps<{
    order: OrderDetailRecord;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'My Orders',
                href: ordersIndex(),
            },
            {
                title: 'Order Details',
                href: '#',
            },
        ],
    },
});

const processingAction = ref<'cancel' | 'retry' | null>(null);

function cancelOrder(): void {
    processingAction.value = 'cancel';
    router.post(
        orderCancel.url(props.order.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingAction.value = null;
            },
        },
    );
}

function retryOrderPayment(): void {
    processingAction.value = 'retry';
    router.post(
        orderRetryPayment.url(props.order.id),
        {},
        {
            onFinish: () => {
                processingAction.value = null;
            },
        },
    );
}

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'paid' || status === 'completed') {
        return 'default';
    }
    if (status === 'cancelled' || status === 'refunded') {
        return 'destructive';
    }

    return 'secondary';
}

// Keep orderShow imported in case deep links reference current route
void orderShow;
</script>

<template>
    <Head :title="`Order ${order.order_number} — XHopy`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1.5">
                <Link
                    :href="ordersIndex()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-primary"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to My Orders</span>
                </Link>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1
                        class="text-2xl font-bold tracking-tight text-foreground"
                    >
                        {{ order.order_number }}
                    </h1>
                    <Badge :variant="statusBadgeVariant(order.status)">
                        {{ order.status_label }}
                    </Badge>
                    <Badge variant="outline">
                        {{ order.payment_status_label }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Placed {{ order.placed_at_diff }}
                    <span v-if="order.paid_at_diff">
                        · Paid {{ order.paid_at_diff }}
                    </span>
                    <span v-if="order.shipped_at_diff">
                        · Shipped {{ order.shipped_at_diff }}
                    </span>
                    <span v-if="order.delivered_at_diff">
                        · Delivered {{ order.delivered_at_diff }}
                    </span>
                    <span v-if="order.payment_method_type" class="uppercase">
                        · {{ order.payment_method_type }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="order.can_retry_payment"
                    type="button"
                    class="h-10 gap-2"
                    :disabled="processingAction !== null"
                    @click="retryOrderPayment"
                >
                    <CreditCard class="size-4" aria-hidden="true" />
                    <span>Complete Payment</span>
                </Button>

                <Button
                    v-if="order.can_cancel"
                    type="button"
                    variant="outline"
                    class="h-10 gap-2 text-destructive hover:text-destructive"
                    :disabled="processingAction !== null"
                    @click="cancelOrder"
                >
                    <XCircle class="size-4" aria-hidden="true" />
                    <span>Cancel Order</span>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Items & Timeline -->
            <div class="space-y-6 lg:col-span-8">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Package
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Order Items Snapshot</span>
                            <Badge
                                v-if="(order.seller_orders?.length ?? 0) > 1"
                                variant="outline"
                                class="text-[11px]"
                            >
                                {{ order.seller_orders?.length }} stores
                            </Badge>
                        </CardTitle>
                        <CardDescription>
                            Prices and product details frozen at order
                            placement.
                            <span v-if="(order.seller_orders?.length ?? 0) > 1">
                                Each seller fulfills and ships their suborder
                                independently.
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="(order.seller_orders?.length ?? 0) > 0"
                            class="space-y-4"
                        >
                            <section
                                v-for="group in order.seller_orders"
                                :key="group.id"
                                :aria-label="`Fulfillment group ${group.seller_order_number}`"
                                class="overflow-hidden rounded-xl border border-border"
                            >
                                <header
                                    class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-secondary/35 px-4 py-3"
                                >
                                    <div
                                        class="flex min-w-0 flex-wrap items-center gap-2"
                                    >
                                        <Store
                                            class="size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        />
                                        <Link
                                            v-if="group.store_url"
                                            :href="group.store_url"
                                            class="truncate text-sm font-bold text-foreground hover:text-primary"
                                        >
                                            {{ group.seller_name }}
                                        </Link>
                                        <span
                                            v-else
                                            class="truncate text-sm font-bold text-foreground"
                                        >
                                            {{ group.seller_name }}
                                        </span>
                                        <Badge
                                            v-if="group.is_official_store"
                                            variant="default"
                                            class="px-1.5 py-0 text-[10px] tracking-wide uppercase"
                                        >
                                            Official
                                        </Badge>
                                        <span
                                            class="font-mono text-xs text-muted-foreground"
                                        >
                                            {{ group.seller_order_number }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2.5">
                                        <Badge
                                            :variant="
                                                statusBadgeVariant(group.status)
                                            "
                                        >
                                            {{ group.status_label }}
                                        </Badge>
                                        <span
                                            class="text-xs font-bold text-foreground tabular-nums"
                                        >
                                            {{ group.total_formatted }}
                                        </span>
                                    </div>
                                </header>

                                <div
                                    v-if="
                                        group.shipping_carrier ||
                                        group.tracking_number
                                    "
                                    class="flex flex-wrap items-center gap-2 border-b border-border bg-secondary/15 px-4 py-2 text-xs text-muted-foreground"
                                >
                                    <Truck
                                        class="size-3.5 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span v-if="group.shipping_carrier">
                                        Carrier:
                                        <strong class="text-foreground">{{
                                            group.shipping_carrier
                                        }}</strong>
                                    </span>
                                    <span v-if="group.tracking_number">
                                        Tracking #:
                                        <strong class="text-foreground">{{
                                            group.tracking_number
                                        }}</strong>
                                    </span>
                                    <span v-if="group.shipped_at_diff">
                                        · Shipped {{ group.shipped_at_diff }}
                                    </span>
                                    <span v-if="group.delivered_at_diff">
                                        · Delivered
                                        {{ group.delivered_at_diff }}
                                    </span>
                                </div>

                                <ul class="divide-y divide-border px-4 text-sm">
                                    <li
                                        v-for="item in group.items"
                                        :key="item.id"
                                        class="flex items-start justify-between gap-4 py-3"
                                    >
                                        <div class="min-w-0 space-y-0.5">
                                            <p
                                                class="font-semibold text-foreground"
                                            >
                                                {{ item.product_name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                <span v-if="item.variant_title"
                                                    >{{
                                                        item.variant_title
                                                    }}
                                                    · </span
                                                >SKU {{ item.sku }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.quantity }} &times;
                                                {{ item.unit_price_formatted }}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 font-bold text-foreground tabular-nums"
                                        >
                                            {{ item.line_total_formatted }}
                                        </span>
                                    </li>
                                </ul>
                            </section>
                        </div>

                        <ul v-else class="divide-y divide-border text-sm">
                            <li
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex items-start justify-between gap-4 py-3"
                            >
                                <div class="min-w-0 space-y-0.5">
                                    <p class="font-semibold text-foreground">
                                        {{ item.product_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        <span v-if="item.variant_title"
                                            >{{ item.variant_title }} · </span
                                        >SKU {{ item.sku }} ·
                                        {{ item.seller_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ item.quantity }} &times;
                                        {{ item.unit_price_formatted }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 font-bold text-foreground tabular-nums"
                                >
                                    {{ item.line_total_formatted }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <History
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Status History</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-3 text-sm">
                            <li
                                v-for="entry in order.status_history"
                                :key="entry.id"
                                class="flex flex-col justify-between gap-1 rounded-xl border border-border bg-secondary/30 px-4 py-3 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <p class="font-semibold text-foreground">
                                        {{ entry.to_status_label }}
                                    </p>
                                    <p
                                        v-if="entry.reason"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ entry.reason }}
                                    </p>
                                </div>
                                <span class="text-xs text-muted-foreground">
                                    {{ entry.created_at_diff }}
                                </span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <!-- Totals & Shipping -->
            <div class="space-y-6 lg:col-span-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Payment Summary</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2.5 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="font-medium tabular-nums">{{
                                order.subtotal_formatted
                            }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Shipping</span>
                            <span class="font-medium tabular-nums">{{
                                order.shipping_formatted
                            }}</span>
                        </div>
                        <Separator />
                        <div
                            class="flex items-center justify-between text-base font-bold"
                        >
                            <span>Total</span>
                            <span class="text-primary tabular-nums">{{
                                order.total_formatted
                            }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <MapPin
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Shipping Snapshot</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p class="font-semibold text-foreground">
                            {{ order.shipping_recipient_name }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ order.shipping_phone }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ order.shipping_address }}
                        </p>
                        <div
                            v-if="
                                order.shipping_carrier || order.tracking_number
                            "
                            class="rounded-lg border border-border bg-secondary/40 p-2.5 text-xs"
                        >
                            <p v-if="order.shipping_carrier">
                                Carrier:
                                <strong>{{ order.shipping_carrier }}</strong>
                            </p>
                            <p v-if="order.tracking_number">
                                Tracking #:
                                <strong>{{ order.tracking_number }}</strong>
                            </p>
                        </div>
                        <p
                            v-if="order.shipping_delivery_notes"
                            class="text-xs text-muted-foreground"
                        >
                            Address note: {{ order.shipping_delivery_notes }}
                        </p>
                        <p
                            v-if="order.notes"
                            class="rounded-lg border border-border bg-secondary/40 p-2.5 text-xs text-muted-foreground"
                        >
                            Order note: {{ order.notes }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
