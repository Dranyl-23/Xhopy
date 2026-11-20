<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    ClipboardList,
    History,
    Package,
    TrendingUp,
} from '@lucide/vue';
import AdminOperationsTabs from '@modules/admin/components/AdminOperationsTabs.vue';
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
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import type {
    AdminAnalyticsSummary,
    AdminAuditLogRow,
} from '@modules/admin/types';

defineProps<{
    analytics: AdminAnalyticsSummary;
    recentAuditLogs: AdminAuditLogRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Operations & Analytics', href: adminOperationsIndex() },
        ],
    },
});
</script>

<template>
    <Head title="Operations & Analytics — Admin" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                >
                    <BarChart3 class="size-6 text-primary" aria-hidden="true" />
                    <span>Operations &amp; Store Analytics</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Server-computed revenue metrics, fulfillment queues, low
                    stock watchlists, and immutable audit history.
                </p>
            </div>

            <Button as-child class="h-11 gap-2">
                <Link :href="adminOrdersIndex({ query: { status: 'paid' } })">
                    <ClipboardList class="size-4" aria-hidden="true" />
                    <span>Fulfill Paid Orders</span>
                </Link>
            </Button>
        </div>

        <AdminOperationsTabs active="operations" />

        <!-- KPI Summary Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Confirmed Revenue</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ analytics.kpis.grossRevenueFormatted }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Avg order value:
                    <strong class="text-foreground">{{
                        analytics.kpis.avgOrderValueFormatted
                    }}</strong>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Confirmed Orders</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ analytics.kpis.confirmedOrdersCount }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Total orders placed:
                    <strong class="text-foreground">{{
                        analytics.kpis.totalOrders
                    }}</strong>
                    · Pending payment:
                    <strong class="text-foreground">{{
                        analytics.kpis.pendingPaymentCount
                    }}</strong>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Fulfillment Queue</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ analytics.kpis.fulfillmentQueueCount }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Shipped / delivered:
                    <strong class="text-foreground">{{
                        analytics.kpis.shippedOrDeliveredCount
                    }}</strong>
                    · Cancelled / refunded:
                    <strong class="text-foreground">{{
                        analytics.kpis.cancelledOrRefundedCount
                    }}</strong>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Low Stock Alerts</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ analytics.kpis.lowStockCount }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Out of stock active products:
                    <strong class="text-destructive">{{
                        analytics.kpis.outOfStockCount
                    }}</strong>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- 7-Day Revenue Trend -->
            <Card class="lg:col-span-7">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <TrendingUp
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <span>7-Day Confirmed Sales Trend</span>
                    </CardTitle>
                    <CardDescription>
                        Daily paid order count and gross revenue over the past 7
                        days.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="day in analytics.dailyTrend"
                        :key="day.date"
                        class="space-y-1 text-sm"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-foreground">{{
                                day.label
                            }}</span>
                            <span class="text-muted-foreground tabular-nums">
                                {{ day.orders }}
                                {{ day.orders === 1 ? 'order' : 'orders' }} ·
                                <strong class="text-foreground">{{
                                    day.revenueFormatted
                                }}</strong>
                            </span>
                        </div>
                        <div
                            class="h-2.5 w-full overflow-hidden rounded-full bg-secondary"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${Math.max(day.percentOfPeak, day.orders > 0 ? 6 : 0)}%`,
                                }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Top Selling Products -->
            <Card class="lg:col-span-5">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Package
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <span>Top Selling Products</span>
                    </CardTitle>
                    <CardDescription>
                        Aggregated from confirmed order snapshots.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="analytics.topSellingProducts.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No confirmed product sales recorded yet.
                    </p>
                    <ul v-else class="divide-y divide-border text-sm">
                        <li
                            v-for="item in analytics.topSellingProducts"
                            :key="`${item.sku}-${item.product_name}`"
                            class="flex items-center justify-between gap-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium text-foreground">
                                    {{ item.product_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    SKU {{ item.sku }} · {{ item.units_sold }}
                                    sold
                                </p>
                            </div>
                            <span
                                class="shrink-0 font-bold text-foreground tabular-nums"
                            >
                                {{ item.gross_sales_formatted }}
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Low Stock Watchlist -->
            <Card class="lg:col-span-5">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <AlertTriangle
                                class="size-4 text-amber-600"
                                aria-hidden="true"
                            />
                            <span>Low Stock Watchlist</span>
                        </CardTitle>
                        <CardDescription>
                            Active products at 5 units or fewer.
                        </CardDescription>
                    </div>
                    <Button as-child variant="outline" size="sm" class="h-9">
                        <Link
                            :href="
                                adminInventoryIndex({
                                    query: { filter: 'low' },
                                })
                            "
                        >
                            Manage Stock
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="analytics.lowStockItems.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        All active catalog products are above the low-stock
                        threshold.
                    </p>
                    <ul v-else class="divide-y divide-border text-sm">
                        <li
                            v-for="row in analytics.lowStockItems"
                            :key="row.product_id"
                            class="flex items-center justify-between gap-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium text-foreground">
                                    {{ row.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    SKU {{ row.sku }}
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    row.stock_quantity === 0
                                        ? 'destructive'
                                        : 'secondary'
                                "
                            >
                                {{ row.stock_quantity }} left
                            </Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Operational & Security Audit Log -->
            <Card class="lg:col-span-7">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <History
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <span>Operational &amp; Security Audit Trail</span>
                    </CardTitle>
                    <CardDescription>
                        Recent order fulfillment updates, stock adjustments, CSV
                        imports, and security events.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-y border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">Action</th>
                                    <th class="px-4 py-3">Actor</th>
                                    <th class="px-4 py-3">Target</th>
                                    <th class="px-4 py-3">When</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="recentAuditLogs.length === 0">
                                    <td
                                        colspan="4"
                                        class="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No operational audit entries recorded
                                        yet.
                                    </td>
                                </tr>
                                <tr
                                    v-for="log in recentAuditLogs"
                                    :key="log.id"
                                    class="hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        {{ log.label }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-muted-foreground"
                                    >
                                        {{ log.actor_name }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-muted-foreground"
                                    >
                                        {{ log.user_name }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-muted-foreground"
                                    >
                                        {{ log.created_at_diff }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
