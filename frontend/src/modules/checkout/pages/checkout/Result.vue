<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    ReceiptText,
    RotateCw,
    XCircle,
} from '@lucide/vue';
import { computed } from 'vue';
import { StorefrontLayout } from '@modules/storefront';
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
import { index as cartIndex } from '@/routes/cart';
import { index as catalogIndex } from '@/routes/catalog';
import {
    index as ordersIndex,
    retryPayment as orderRetryPayment,
    show as orderShow,
} from '@/routes/orders';
import type { CheckoutResultProps } from '@modules/checkout/types';

const props = defineProps<CheckoutResultProps>();

const isConfirmedPaid = computed(() => props.order.payment_status === 'paid');
const isPendingVerification = computed(
    () =>
        props.outcome === 'success' && props.order.payment_status === 'pending',
);
</script>

<template>
    <Head :title="`Order Result — ${order.order_number}`" />

    <StorefrontLayout
        :cart="{ itemCount: 0, subtotalFormatted: null }"
        @search="
            (q) =>
                router.get(catalogIndex.url({ query: { q: q || undefined } }))
        "
        @select-category="
            (c) =>
                router.get(
                    catalogIndex.url({ query: { category: c || undefined } }),
                )
        "
        @open-cart="router.get(cartIndex.url())"
    >
        <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
            <Card>
                <CardHeader class="space-y-3 text-center">
                    <div
                        class="mx-auto flex size-14 items-center justify-center rounded-full bg-secondary"
                    >
                        <CheckCircle2
                            v-if="isConfirmedPaid"
                            class="size-8 text-emerald-600"
                            aria-hidden="true"
                        />
                        <Clock
                            v-else-if="isPendingVerification"
                            class="size-8 text-amber-600"
                            aria-hidden="true"
                        />
                        <XCircle
                            v-else
                            class="size-8 text-destructive"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="space-y-1">
                        <CardTitle class="text-2xl font-extrabold">
                            {{
                                isConfirmedPaid
                                    ? 'Payment Confirmed'
                                    : isPendingVerification
                                      ? 'Payment Awaiting Confirmation'
                                      : 'Checkout Not Completed'
                            }}
                        </CardTitle>
                        <CardDescription>
                            {{
                                isConfirmedPaid
                                    ? 'PayMongo confirmed your payment and your order is now queued for fulfillment.'
                                    : isPendingVerification
                                      ? 'Your return redirect was received, and XHopy is waiting for final payment confirmation from PayMongo.'
                                      : 'Payment was cancelled, failed, or expired. Any expired reservation automatically releases reserved stock.'
                            }}
                        </CardDescription>
                    </div>
                </CardHeader>

                <CardContent class="space-y-5">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-secondary/40 px-4 py-3 text-sm"
                    >
                        <div>
                            <p class="font-semibold text-foreground">
                                {{ order.order_number }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ order.shipping_recipient_name }} ·
                                {{ order.shipping_address }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline">
                                {{ order.status_label }}
                            </Badge>
                            <Badge
                                :variant="
                                    isConfirmedPaid ? 'default' : 'secondary'
                                "
                            >
                                {{ order.payment_status_label }}
                            </Badge>
                        </div>
                    </div>

                    <ul class="divide-y divide-border text-sm">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-4 py-2.5"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium text-foreground">
                                    {{ item.product_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Qty {{ item.quantity }}
                                    <span v-if="item.variant_title">
                                        · {{ item.variant_title }}
                                    </span>
                                </p>
                            </div>
                            <span
                                class="shrink-0 font-semibold text-foreground tabular-nums"
                            >
                                {{ item.line_total_formatted }}
                            </span>
                        </li>
                    </ul>

                    <Separator />

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground">
                            Order Total
                        </span>
                        <span
                            class="text-lg font-extrabold text-primary tabular-nums"
                        >
                            {{ order.total_formatted }}
                        </span>
                    </div>

                    <div class="flex flex-wrap justify-between gap-2 pt-2">
                        <Button as-child variant="outline" class="h-11">
                            <Link :href="ordersIndex()">All My Orders</Link>
                        </Button>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="order.status === 'pending_payment'"
                                type="button"
                                variant="secondary"
                                class="h-11 gap-2"
                                @click="
                                    router.post(orderRetryPayment.url(order.id))
                                "
                            >
                                <RotateCw class="size-4" aria-hidden="true" />
                                <span>Retry Payment</span>
                            </Button>

                            <Button as-child class="h-11 gap-2">
                                <Link :href="orderShow(order.id)">
                                    <ReceiptText
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>View Order</span>
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </StorefrontLayout>
</template>
