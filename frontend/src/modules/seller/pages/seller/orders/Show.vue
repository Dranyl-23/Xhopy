<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    History,
    MapPin,
    Package,
    ReceiptText,
    ShieldCheck,
    Truck,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@shared/components/InputError.vue';
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
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField } from '@shared/components/ui/select';
import { Separator } from '@shared/components/ui/separator';
import { dashboard as sellerDashboard } from '@/routes/seller';
import { index as sellerOrdersIndex } from '@/routes/seller/orders';
import { update as sellerOrderStatusUpdate } from '@/routes/seller/orders/status';
import type { SellerOrderDetail } from '@modules/seller/types';

const props = defineProps<{
    sellerOrder: SellerOrderDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Seller Portal', href: sellerDashboard() },
            { title: 'Orders & Fulfillment', href: sellerOrdersIndex() },
            { title: 'Fulfillment Details', href: '#' },
        ],
    },
});

const selectedStatus = ref<string>(
    props.sellerOrder.allowed_transitions[0]?.value ?? '',
);

const form = useForm<{
    status: string;
    shipping_carrier: string;
    tracking_number: string;
    reason: string;
}>({
    status: props.sellerOrder.allowed_transitions[0]?.value ?? '',
    shipping_carrier: props.sellerOrder.shipping_carrier ?? '',
    tracking_number: props.sellerOrder.tracking_number ?? '',
    reason: '',
});

watch(
    () => props.sellerOrder.allowed_transitions,
    (transitions) => {
        selectedStatus.value = transitions[0]?.value ?? '';
        form.status = selectedStatus.value;
    },
);

const generalError = computed<string | null>(() => {
    const first = Object.values(form.errors).find(
        (msg): msg is string => typeof msg === 'string' && msg !== '',
    );

    return first ?? null;
});

function submitTransition(): void {
    form.status = selectedStatus.value;
    if (!form.status) {
        return;
    }

    form.patch(sellerOrderStatusUpdate.url(props.sellerOrder.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedStatus.value =
                props.sellerOrder.allowed_transitions[0]?.value ?? '';
            form.reason = '';
        },
    });
}
</script>

<template>
    <Head
        :title="`Fulfill ${sellerOrder.seller_order_number} — ${sellerOrder.seller_name}`"
    />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1.5">
                <Link
                    :href="sellerOrdersIndex()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-primary"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to Seller Orders</span>
                </Link>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ sellerOrder.seller_order_number }}
                    </h1>
                    <Badge>{{ sellerOrder.status_label }}</Badge>
                    <Badge variant="outline">{{
                        sellerOrder.payment_status_label
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Parent Order
                    <strong class="text-foreground">{{
                        sellerOrder.parent_order_number
                    }}</strong>
                    · Placed {{ sellerOrder.placed_at_diff }}
                    <span v-if="sellerOrder.paid_at_diff">
                        · Paid {{ sellerOrder.paid_at_diff }}
                    </span>
                </p>
            </div>

            <div class="text-left sm:text-right">
                <p class="text-xs text-muted-foreground">Store Subtotal</p>
                <p class="text-2xl font-extrabold text-primary tabular-nums">
                    {{ sellerOrder.total_formatted }}
                </p>
            </div>
        </div>

        <Alert v-if="generalError" variant="destructive">
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>Transition Rejected</AlertTitle>
            <AlertDescription>{{ generalError }}</AlertDescription>
        </Alert>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-8">
                <Card v-if="sellerOrder.allowed_transitions.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Truck
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Update Store Fulfillment</span>
                        </CardTitle>
                        <CardDescription>
                            Advance your store fulfillment independently once
                            payment is confirmed. Post-payment cancellations and
                            refunds are governed by Platform Admin.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            class="space-y-4"
                            @submit.prevent="submitTransition"
                        >
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label for="seller-order-next-status">
                                        Next Status
                                    </Label>
                                    <SelectField
                                        id="seller-order-next-status"
                                        v-model="selectedStatus"
                                        :options="
                                            sellerOrder.allowed_transitions
                                        "
                                        aria-label="Select next fulfillment status"
                                    />
                                    <InputError :message="form.errors.status" />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="seller-order-reason">
                                        Fulfillment Note (Optional)
                                    </Label>
                                    <Input
                                        id="seller-order-reason"
                                        v-model="form.reason"
                                        placeholder="e.g., Packed & scheduled courier pickup"
                                        class="h-11"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="selectedStatus === 'shipped'"
                                class="grid gap-4 rounded-xl border border-border bg-secondary/30 p-4 sm:grid-cols-2"
                            >
                                <div class="space-y-1.5">
                                    <Label for="seller-order-carrier">
                                        Shipping Carrier
                                    </Label>
                                    <Input
                                        id="seller-order-carrier"
                                        v-model="form.shipping_carrier"
                                        placeholder="e.g., LBC Express / J&T"
                                        class="h-11"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="seller-order-tracking">
                                        Tracking Number
                                    </Label>
                                    <Input
                                        id="seller-order-tracking"
                                        v-model="form.tracking_number"
                                        placeholder="e.g., TRK-2026-MV5"
                                        class="h-11"
                                    />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                class="h-11"
                                :disabled="form.processing || !selectedStatus"
                            >
                                Update Fulfillment Status
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Alert v-else>
                    <ShieldCheck class="size-4" aria-hidden="true" />
                    <AlertTitle>No Pending Seller Actions</AlertTitle>
                    <AlertDescription>
                        This suborder is currently in
                        <strong>{{ sellerOrder.status_label }}</strong> state.
                        Unpaid orders await PayMongo confirmation, while
                        completed or terminal states are read-only.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Package
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Your Store Items Snapshot</span>
                        </CardTitle>
                        <CardDescription>
                            Line items strictly scoped to
                            {{ sellerOrder.seller_name }}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y divide-border text-sm">
                            <li
                                v-for="item in sellerOrder.items"
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
                                        >SKU {{ item.sku }}
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
                            <span>Fulfillment History</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-3 text-sm">
                            <li
                                v-for="entry in sellerOrder.status_history"
                                :key="entry.id"
                                class="flex flex-col justify-between gap-1 rounded-xl border border-border bg-secondary/30 px-4 py-3 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <p class="font-semibold text-foreground">
                                        {{ entry.to_status_label }}
                                        <span
                                            class="text-xs font-normal text-muted-foreground"
                                        >
                                            by {{ entry.actor_name }}
                                        </span>
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

            <div class="space-y-6 lg:col-span-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <ReceiptText
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Suborder Summary</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums">{{
                                sellerOrder.subtotal_formatted
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Shipping</span>
                            <span class="tabular-nums">{{
                                sellerOrder.shipping_formatted
                            }}</span>
                        </div>
                        <Separator />
                        <div class="flex justify-between text-base font-bold">
                            <span>Store Total</span>
                            <span class="text-primary tabular-nums">{{
                                sellerOrder.total_formatted
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
                            <span>Delivery Destination</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p class="font-semibold text-foreground">
                            {{ sellerOrder.shipping_recipient_name }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ sellerOrder.shipping_phone }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ sellerOrder.shipping_address }}
                        </p>
                        <div
                            v-if="
                                sellerOrder.shipping_carrier ||
                                sellerOrder.tracking_number
                            "
                            class="rounded-lg border border-border bg-secondary/40 p-3 text-xs"
                        >
                            <p v-if="sellerOrder.shipping_carrier">
                                Carrier:
                                <strong>{{
                                    sellerOrder.shipping_carrier
                                }}</strong>
                            </p>
                            <p v-if="sellerOrder.tracking_number">
                                Tracking #:
                                <strong>{{
                                    sellerOrder.tracking_number
                                }}</strong>
                            </p>
                        </div>
                        <p
                            v-if="sellerOrder.shipping_delivery_notes"
                            class="text-xs text-muted-foreground"
                        >
                            Address note:
                            {{ sellerOrder.shipping_delivery_notes }}
                        </p>
                        <p
                            v-if="sellerOrder.order_notes"
                            class="rounded-lg border border-border bg-secondary/30 p-2.5 text-xs text-muted-foreground"
                        >
                            Customer note: {{ sellerOrder.order_notes }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
