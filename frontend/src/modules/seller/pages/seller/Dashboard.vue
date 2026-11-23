<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    Building2,
    CheckCircle2,
    ClipboardList,
    Clock3,
    Package,
    ReceiptText,
    ShieldCheck,
    Store,
} from '@lucide/vue';
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
import { onboarding as sellerOnboarding } from '@/routes/seller';
import { index as sellerInventoryIndex } from '@/routes/seller/inventory';
import { index as sellerOrdersIndex } from '@/routes/seller/orders';
import {
    create as sellerProductsCreate,
    index as sellerProductsIndex,
} from '@/routes/seller/products';
import { edit as sellerStoreEdit } from '@/routes/seller/store';
import type { SellerDashboardMetrics } from '@modules/seller/types';

defineProps<{
    store: {
        id: number;
        name: string;
        slug: string;
        status: string;
        status_label: string;
        contact_email: string | null;
        contact_phone: string | null;
        description: string | null;
    };
    profile: {
        id: number;
        business_name: string;
        business_type: string;
        status: string;
        status_label: string;
        approved_at: string | null;
    };
    metrics: SellerDashboardMetrics;
}>();
</script>

<template>
    <Head :title="`${store.name} — Seller Dashboard`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2.5">
                    <Building2 class="size-5 text-primary" aria-hidden="true" />
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ store.name }}
                    </h1>
                    <Badge variant="default">{{ store.status_label }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ profile.business_name }} · Handle:
                    <span class="font-mono text-foreground">{{
                        store.slug
                    }}</span>
                    <span v-if="profile.approved_at">
                        · Approved {{ profile.approved_at }}</span
                    >
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button as-child class="h-10 gap-1.5">
                    <Link :href="sellerProductsCreate()">
                        <Package class="size-4" aria-hidden="true" />
                        <span>Add Product</span>
                    </Link>
                </Button>
                <Button as-child variant="outline" class="h-10 gap-1.5">
                    <Link :href="sellerOrdersIndex()">
                        <ClipboardList class="size-4" aria-hidden="true" />
                        <span>Orders</span>
                    </Link>
                </Button>
                <Button as-child variant="outline" class="h-10 gap-1.5">
                    <Link :href="sellerInventoryIndex()">
                        <Boxes class="size-4" aria-hidden="true" />
                        <span>Inventory</span>
                    </Link>
                </Button>
                <Button as-child variant="outline" class="h-10 gap-1.5">
                    <Link :href="sellerStoreEdit()">
                        <Store class="size-4" aria-hidden="true" />
                        <span>Store Profile</span>
                    </Link>
                </Button>
            </div>
        </div>

        <Alert v-if="metrics.rejected_products > 0" variant="destructive">
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>
                {{ metrics.rejected_products }} Product(s) Need Changes
            </AlertTitle>
            <AlertDescription
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>
                    Admin moderation requested updates on one or more listings
                    before they can go live.
                </span>
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9 self-start sm:self-auto"
                >
                    <Link
                        :href="
                            sellerProductsIndex({
                                query: { status: 'rejected' },
                            })
                        "
                    >
                        Review Feedback
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Store Products</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <Package
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ metrics.total_products }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Total catalog listings assigned to {{ store.name }}.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Active Live</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <CheckCircle2
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ metrics.active_products }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Moderation-approved products live on the storefront.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Pending Moderation</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <Clock3
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ metrics.pending_review_products }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Submitted listings awaiting Admin approval.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Low Stock Alerts</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <AlertTriangle
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ metrics.low_stock_products }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Store SKUs with 5 or fewer units remaining.
                </CardContent>
            </Card>

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
                        <span>{{
                            metrics.seller_orders_count ??
                            metrics.order_lines_count
                        }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    {{ metrics.pending_fulfillment_orders ?? 0 }} awaiting
                    fulfillment
                    <span v-if="metrics.confirmed_revenue_formatted">
                        · {{ metrics.confirmed_revenue_formatted }} confirmed
                    </span>
                </CardContent>
            </Card>
        </div>

        <Alert>
            <ShieldCheck class="size-4" aria-hidden="true" />
            <AlertTitle>Catalog Moderation Policy</AlertTitle>
            <AlertDescription
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <span>
                    New seller listings and catalog content edits are submitted
                    for Admin moderation before appearing live. Manage your
                    inventory counts directly at any time.
                </span>
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9 shrink-0"
                >
                    <Link :href="sellerOnboarding()">
                        Verification Details
                    </Link>
                </Button>
            </AlertDescription>
        </Alert>
    </div>
</template>
