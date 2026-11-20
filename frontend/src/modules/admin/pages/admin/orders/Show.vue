<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CreditCard,
    History,
    MapPin,
    Package,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { dashboard } from '@/routes';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { update as adminOrderStatusUpdate } from '@/routes/admin/orders/status';
import type { AdminOrderDetail } from '@modules/admin/types';

const props = defineProps<{
    order: AdminOrderDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Admin Orders', href: adminOrdersIndex() },
            { title: 'Manage Order', href: '#' },
        ],
    },
});

const selectedStatus = ref<string>(
    props.order.allowed_transitions[0]?.value ?? '',
);

const form = useForm<{
    status: string;
    shipping_carrier: string;
    tracking_number: string;
    reason: string;
}>({
    status: props.order.allowed_transitions[0]?.value ?? '',
    shipping_carrier: props.order.shipping_carrier ?? '',
    tracking_number: props.order.tracking_number ?? '',
    reason: '',
});

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

    form.patch(adminOrderStatusUpdate.url(props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedStatus.value =
                props.order.allowed_transitions[0]?.value ?? '';
            form.reason = '';
        },
    });
}
</script>

<template>
    <Head :title="`Manage ${order.order_number} — Admin`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1.5">
                <Link
                    :href="adminOrdersIndex()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-primary"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to Admin Orders</span>
                </Link>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ order.order_number }}
                    </h1>
                    <Badge>{{ order.status_label }}</Badge>
                    <Badge variant="outline">{{
                        order.payment_status_label
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Customer:
                    <strong class="text-foreground">{{
                        order.customer.name
                    }}</strong>
                    ({{ order.customer.email }}) · Placed
                    {{ order.placed_at_diff }}
                </p>
            </div>

            <div class="text-left sm:text-right">
                <p class="text-xs text-muted-foreground">Order Total</p>
                <p class="text-2xl font-extrabold text-primary tabular-nums">
                    {{ order.total_formatted }}
                </p>
            </div>
        </div>

        <Alert v-if="generalError" variant="destructive">
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>Transition Rejected</AlertTitle>
            <AlertDescription>{{ generalError }}</AlertDescription>
        </Alert>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Left: Transitions + Items + Timeline -->
            <div class="space-y-6 lg:col-span-8">
                <Card v-if="order.allowed_transitions.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Truck
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Fulfillment &amp; Status Transition</span>
                        </CardTitle>
                        <CardDescription>
                            Only valid transitions permitted by the server state
                            machine and your role are enabled. Customers are
                            automatically notified by email and in-app alert.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            class="space-y-4"
                            @submit.prevent="submitTransition"
                        >
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label for="admin-order-next-status">
                                        Next Status
                                    </Label>
                                    <SelectField
                                        id="admin-order-next-status"
                                        v-model="selectedStatus"
                                        :options="order.allowed_transitions"
                                        aria-label="Select next order status"
                                    />
                                    <InputError :message="form.errors.status" />
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="admin-order-reason">
                                        Transition Note (Optional)
                                    </Label>
                                    <Input
                                        id="admin-order-reason"
                                        v-model="form.reason"
                                        placeholder="e.g., Packed & handed to courier"
                                        class="h-11"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="selectedStatus === 'shipped'"
                                class="grid gap-4 rounded-xl border border-border bg-secondary/30 p-4 sm:grid-cols-2"
                            >
                                <div class="space-y-1.5">
                                    <Label for="admin-order-carrier">
                                        Shipping Carrier
                                    </Label>
                                    <Input
                                        id="admin-order-carrier"
                                        v-model="form.shipping_carrier"
                                        placeholder="e.g., LBC Express / J&T"
                                        class="h-11"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="admin-order-tracking">
                                        Tracking Number
                                    </Label>
                                    <Input
                                        id="admin-order-tracking"
                                        v-model="form.tracking_number"
                                        placeholder="e.g., TRK-2026-99104"
                                        class="h-11"
                                    />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                class="h-11"
                                :disabled="form.processing || !selectedStatus"
                            >
                                Update Order Status
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Package
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Order Line Items</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y divide-border text-sm">
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

            <!-- Right: Payment & Shipping Snapshot -->
            <div class="space-y-6 lg:col-span-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <CreditCard
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Payments</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums">{{
                                order.subtotal_formatted
                            }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Shipping</span>
                            <span class="tabular-nums">{{
                                order.shipping_formatted
                            }}</span>
                        </div>
                        <Separator />
                        <div
                            v-for="payment in order.payments"
                            :key="payment.id"
                            class="space-y-1 rounded-lg border border-border bg-secondary/30 p-3 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <strong class="uppercase">{{
                                    payment.provider
                                }}</strong>
                                <Badge variant="outline">{{
                                    payment.status_label
                                }}</Badge>
                            </div>
                            <p class="text-muted-foreground">
                                Ref: {{ payment.provider_reference ?? '—' }}
                            </p>
                            <p class="text-muted-foreground">
                                Amount: {{ payment.amount_formatted }}
                                <span
                                    v-if="payment.payment_method_type"
                                    class="uppercase"
                                >
                                    · {{ payment.payment_method_type }}
                                </span>
                            </p>
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
                            <span>Shipping &amp; Tracking</span>
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
                            class="rounded-lg border border-border bg-secondary/40 p-3 text-xs"
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
                            v-if="order.notes"
                            class="rounded-lg border border-border bg-secondary/30 p-2.5 text-xs text-muted-foreground"
                        >
                            Order note: {{ order.notes }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
