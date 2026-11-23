<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    Clock3,
    Package,
    ReceiptText,
    Search,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { PaginationBar } from '@modules/catalog';
import EmptyState from '@shared/components/EmptyState.vue';
import LoadingState from '@shared/components/LoadingState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { SelectField } from '@shared/components/ui/select';
import { dashboard as sellerDashboard } from '@/routes/seller';
import {
    index as sellerOrdersIndex,
    show as sellerOrderShow,
} from '@/routes/seller/orders';
import type { SellerOrdersIndexProps } from '@modules/seller/types';

const props = defineProps<SellerOrdersIndexProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Seller Portal', href: sellerDashboard() },
            { title: 'Orders & Fulfillment', href: sellerOrdersIndex() },
        ],
    },
});

const searchInput = ref(props.filters.q);
const statusFilter = ref(props.filters.status);
const isLoading = ref(false);

const paginationMeta = computed(() => ({
    currentPage: props.sellerOrders.current_page,
    lastPage: props.sellerOrders.last_page,
    perPage: props.sellerOrders.per_page,
    total: props.sellerOrders.total,
}));

function applyFilter(pageNumber = 1): void {
    isLoading.value = true;
    router.get(
        sellerOrdersIndex.url({
            query: {
                q: searchInput.value.trim() || undefined,
                status: statusFilter.value || undefined,
                page: pageNumber > 1 ? pageNumber : undefined,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'paid' || status === 'delivered') {
        return 'default';
    }
    if (
        status === 'cancelled' ||
        status === 'refunded' ||
        status === 'expired'
    ) {
        return 'destructive';
    }

    return 'secondary';
}
</script>

<template>
    <Head :title="`Orders & Fulfillment — ${store.name}`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight">
                    Orders &amp; Fulfillment
                </h1>
                <p class="text-sm text-muted-foreground">
                    Fulfill orders scoped exclusively to
                    <strong class="text-foreground">{{ store.name }}</strong>
                    and attach tracking numbers upon shipment.
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Store Suborders</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <ReceiptText
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ stats.totalOrders }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    All suborders assigned to your store.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>To Fulfill</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <Clock3
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ stats.toFulfillCount }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Paid &amp; processing queues ready for dispatch.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Shipped</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <Truck class="size-5 text-primary" aria-hidden="true" />
                        <span>{{ stats.shippedCount }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    In transit with courier details attached.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Delivered</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <CheckCircle2
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ stats.deliveredCount }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Completed customer deliveries.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Confirmed Store Sales</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <Package
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ stats.confirmedRevenueFormatted }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Gross subtotal across paid &amp; fulfilled suborders.
                </CardContent>
            </Card>
        </div>

        <form
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
            @submit.prevent="applyFilter(1)"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="searchInput"
                    type="search"
                    aria-label="Search suborders"
                    placeholder="Search suborder #, parent order #, or recipient..."
                    class="h-10 pl-9"
                />
            </div>

            <SelectField
                id="seller-orders-status-filter"
                v-model="statusFilter"
                :options="statuses"
                empty-option-label="All Statuses"
                aria-label="Filter suborders by status"
                trigger-class="w-full sm:w-[190px]"
                @change="applyFilter(1)"
            />

            <Button type="submit" class="h-10 px-5">Filter</Button>
        </form>

        <Card>
            <CardContent class="p-0">
                <div v-if="isLoading" class="p-6">
                    <LoadingState :count="4" label="Loading store orders..." />
                </div>
                <div v-else-if="sellerOrders.data.length === 0" class="p-6">
                    <EmptyState
                        :icon="ClipboardList"
                        title="No store suborders found"
                        description="Orders containing items from your store will appear here once placed at checkout."
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Suborder</th>
                                <th class="px-4 py-3">Recipient</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Payment</th>
                                <th class="px-4 py-3">Tracking</th>
                                <th class="px-4 py-3">Store Subtotal</th>
                                <th class="px-4 py-3">Placed</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="row in sellerOrders.data"
                                :key="row.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-foreground">
                                        {{ row.seller_order_number }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Order {{ row.parent_order_number }} ·
                                        {{ row.items_count }}
                                        {{
                                            row.items_count === 1
                                                ? 'item'
                                                : 'items'
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-medium text-foreground">
                                        {{ row.recipient_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.destination_city }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge
                                        :variant="
                                            statusBadgeVariant(row.status)
                                        "
                                    >
                                        {{ row.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge variant="outline">
                                        {{ row.payment_status_label }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    <span v-if="row.tracking_number">
                                        {{ row.shipping_carrier ?? 'Courier' }}:
                                        <strong class="text-foreground">{{
                                            row.tracking_number
                                        }}</strong>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td
                                    class="px-4 py-3.5 font-bold text-foreground tabular-nums"
                                >
                                    {{ row.total_formatted }}
                                </td>
                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ row.placed_at_diff }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="h-9 gap-1"
                                    >
                                        <Link :href="sellerOrderShow(row.id)">
                                            <span>Manage</span>
                                            <ChevronRight
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <PaginationBar
            :meta="paginationMeta"
            @change-page="applyFilter($event)"
        />
    </div>
</template>
