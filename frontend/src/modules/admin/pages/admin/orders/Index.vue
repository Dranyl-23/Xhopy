<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckSquare, ChevronRight, ClipboardList, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminOperationsTabs from '@modules/admin/components/AdminOperationsTabs.vue';
import { PaginationBar } from '@modules/catalog';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Card, CardContent } from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { SelectField } from '@shared/components/ui/select';
import { dashboard } from '@/routes';
import {
    bulkTransition as adminOrdersBulkTransition,
    index as adminOrdersIndex,
    show as adminOrderShow,
} from '@/routes/admin/orders';
import type {
    AdminOrderListRow,
    AdminStatusOption,
} from '@modules/admin/types';

const props = defineProps<{
    orders: {
        data: AdminOrderListRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    statuses: AdminStatusOption[];
    filters: {
        q: string;
        status: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Admin Orders', href: adminOrdersIndex() },
        ],
    },
});

const searchInput = ref(props.filters.q);
const statusFilter = ref(props.filters.status);
const selectedOrderIds = ref<number[]>([]);
const bulkProcessing = ref(false);

const paginationMeta = computed(() => ({
    currentPage: props.orders.current_page,
    lastPage: props.orders.last_page,
    perPage: props.orders.per_page,
    total: props.orders.total,
}));

function applyFilter(pageNumber = 1): void {
    router.get(
        adminOrdersIndex.url({
            query: {
                q: searchInput.value.trim() || undefined,
                status: statusFilter.value || undefined,
                page: pageNumber > 1 ? pageNumber : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function toggleOrderSelection(orderId: number): void {
    if (selectedOrderIds.value.includes(orderId)) {
        selectedOrderIds.value = selectedOrderIds.value.filter(
            (id) => id !== orderId,
        );
    } else {
        selectedOrderIds.value = [...selectedOrderIds.value, orderId];
    }
}

function submitBulkProcessing(): void {
    if (selectedOrderIds.value.length === 0) {
        return;
    }

    bulkProcessing.value = true;
    router.post(
        adminOrdersBulkTransition.url(),
        {
            order_ids: selectedOrderIds.value,
            status: 'processing',
            reason: 'Batch queued for fulfillment processing.',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedOrderIds.value = [];
            },
            onFinish: () => {
                bulkProcessing.value = false;
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
    if (status === 'cancelled' || status === 'refunded') {
        return 'destructive';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="Orders & Fulfillment — Admin" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
            <h1
                class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
            >
                <ClipboardList class="size-6 text-primary" aria-hidden="true" />
                <span>Orders &amp; Fulfillment</span>
            </h1>
            <p class="text-sm text-muted-foreground">
                Inspect customer orders, verify PayMongo payment state, and
                execute guarded fulfillment transitions.
            </p>
        </div>

        <AdminOperationsTabs active="orders" />

        <form
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
            @submit.prevent="applyFilter()"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="searchInput"
                    placeholder="Search order #, recipient, or customer email..."
                    aria-label="Search orders"
                    class="h-11 pl-9"
                />
            </div>

            <SelectField
                v-model="statusFilter"
                :options="statuses"
                empty-option-label="All Statuses"
                aria-label="Filter orders by status"
                trigger-class="sm:w-[190px]"
                @change="applyFilter()"
            />

            <Button type="submit" variant="outline" class="h-11">
                Filter
            </Button>
        </form>

        <!-- Safe Bulk Transition Toolbar -->
        <div
            v-if="selectedOrderIds.length > 0"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3 text-sm"
        >
            <div class="flex items-center gap-2 font-medium text-foreground">
                <CheckSquare class="size-4 text-primary" aria-hidden="true" />
                <span>{{ selectedOrderIds.length }} order(s) selected</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    class="h-10"
                    :disabled="bulkProcessing"
                    @click="submitBulkProcessing"
                >
                    Bulk Advance Paid &rarr; Processing
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-10"
                    @click="selectedOrderIds = []"
                >
                    Clear
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="w-10 px-4 py-3">
                                    <span class="sr-only">Select</span>
                                </th>
                                <th class="px-4 py-3">Order</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Order Status</th>
                                <th class="px-4 py-3">Payment</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Placed</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="orders.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No orders matched your current filters.
                                </td>
                            </tr>
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3.5">
                                    <input
                                        type="checkbox"
                                        :checked="
                                            selectedOrderIds.includes(order.id)
                                        "
                                        :aria-label="`Select ${order.order_number}`"
                                        class="size-4 accent-primary"
                                        @change="toggleOrderSelection(order.id)"
                                    />
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-foreground">
                                        {{ order.order_number }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ order.items_count }}
                                        {{
                                            order.items_count === 1
                                                ? 'item'
                                                : 'items'
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-medium text-foreground">
                                        {{ order.customer_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ order.customer_email }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge
                                        :variant="
                                            statusBadgeVariant(order.status)
                                        "
                                    >
                                        {{ order.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge variant="outline">
                                        {{ order.payment_status_label }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-4 py-3.5 font-bold text-foreground tabular-nums"
                                >
                                    {{ order.total_formatted }}
                                </td>
                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ order.placed_at_diff }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="h-9 gap-1"
                                    >
                                        <Link :href="adminOrderShow(order.id)">
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
            @change-page="(nextPage) => applyFilter(nextPage)"
        />
    </div>
</template>
