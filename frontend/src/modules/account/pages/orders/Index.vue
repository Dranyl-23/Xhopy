<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, ChevronRight, ReceiptText, ShoppingBag } from '@lucide/vue';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { dashboard } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import { index as ordersIndex, show as orderShow } from '@/routes/orders';
import { read as ordersNotificationsRead } from '@/routes/orders/notifications';
import type {
    CustomerNotificationEntry,
    OrderListItem,
} from '@modules/account/types';

defineProps<{
    orders: {
        data: OrderListItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    notifications?: CustomerNotificationEntry[];
    unreadNotificationsCount?: number;
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
        ],
    },
});

function markNotificationsRead(): void {
    router.post(
        ordersNotificationsRead.url(),
        {},
        {
            preserveScroll: true,
        },
    );
}

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'paid' || status === 'delivered') {
        return 'default';
    }
    if (status === 'cancelled' || status === 'refunded') {
        return 'destructive';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="My Orders — XHopy" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    My Orders
                </h1>
                <p class="text-sm text-muted-foreground">
                    View order status, PayMongo payment confirmation, shipment
                    tracking, and historical snapshots.
                </p>
            </div>
            <Button as-child variant="outline" class="h-10 gap-2">
                <Link :href="catalogIndex()">
                    <ShoppingBag class="size-4" aria-hidden="true" />
                    <span>Browse Catalog</span>
                </Link>
            </Button>
        </div>

        <Card v-if="notifications && notifications.length > 0">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3 pb-3"
            >
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Bell class="size-4 text-primary" aria-hidden="true" />
                        <span>Order Notifications</span>
                        <Badge
                            v-if="(unreadNotificationsCount ?? 0) > 0"
                            variant="default"
                        >
                            {{ unreadNotificationsCount }} unread
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        Real-time order confirmation and fulfillment updates.
                    </CardDescription>
                </div>

                <Button
                    v-if="(unreadNotificationsCount ?? 0) > 0"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9"
                    @click="markNotificationsRead"
                >
                    Mark All Read
                </Button>
            </CardHeader>
            <CardContent class="space-y-2">
                <div
                    v-for="entry in notifications"
                    :key="entry.id"
                    :class="[
                        'flex flex-col justify-between gap-2 rounded-xl border px-4 py-3 text-sm sm:flex-row sm:items-center',
                        entry.is_unread
                            ? 'border-primary/40 bg-primary/5'
                            : 'border-border bg-secondary/20',
                    ]"
                >
                    <div class="space-y-0.5">
                        <p class="font-semibold text-foreground">
                            {{ entry.title }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ entry.message }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="text-xs text-muted-foreground">
                            {{ entry.created_at_diff }}
                        </span>
                        <Link
                            v-if="entry.action_url"
                            :href="entry.action_url"
                            class="text-xs font-semibold text-primary hover:underline"
                        >
                            Open
                        </Link>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-if="orders.data.length > 0" class="space-y-4">
            <Card v-for="order in orders.data" :key="order.id">
                <CardHeader
                    class="flex flex-col justify-between gap-3 pb-3 sm:flex-row sm:items-center"
                >
                    <div class="space-y-1">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <ReceiptText
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>{{ order.order_number }}</span>
                        </CardTitle>
                        <CardDescription>
                            Placed {{ order.placed_at_diff }} ·
                            {{ order.items_count }}
                            {{ order.items_count === 1 ? 'item' : 'items' }}
                            <span v-if="(order.seller_orders_count ?? 0) > 1">
                                · {{ order.seller_orders_count }} stores
                            </span>
                        </CardDescription>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="statusBadgeVariant(order.status)">
                            {{ order.status_label }}
                        </Badge>
                        <Badge variant="outline">
                            {{ order.payment_status_label }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border pt-4"
                >
                    <div>
                        <span class="text-xs text-muted-foreground">Total</span>
                        <p class="text-lg font-bold text-primary tabular-nums">
                            {{ order.total_formatted }}
                        </p>
                    </div>

                    <Button as-child variant="outline" class="h-10 gap-1.5">
                        <Link :href="orderShow(order.id)">
                            <span>View Order Details</span>
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <div
                v-if="orders.last_page > 1"
                class="flex items-center justify-between pt-2 text-sm text-muted-foreground"
            >
                <span>
                    Page {{ orders.current_page }} of {{ orders.last_page }} ({{
                        orders.total
                    }}
                    total orders)
                </span>

                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9"
                        :disabled="orders.current_page <= 1"
                        @click="
                            router.get(
                                ordersIndex.url({
                                    query: { page: orders.current_page - 1 },
                                }),
                            )
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9"
                        :disabled="orders.current_page >= orders.last_page"
                        @click="
                            router.get(
                                ordersIndex.url({
                                    query: { page: orders.current_page + 1 },
                                }),
                            )
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            :icon="ReceiptText"
            title="No orders placed yet"
            description="Once you place an order through XHopy checkout, it will appear here with live payment and status updates."
            action-label="Explore Catalog"
            @action="router.get(catalogIndex.url())"
        />
    </div>
</template>
