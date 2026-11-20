<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock, ShieldCheck, XCircle } from '@lucide/vue';
import { ref } from 'vue';
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
import { index as cartIndex } from '@/routes/cart';
import { index as catalogIndex } from '@/routes/catalog';
import { simulate as checkoutSandboxSimulate } from '@/routes/checkout/sandbox';
import { show as orderShow } from '@/routes/orders';
import type { CheckoutSandboxProps } from '@modules/checkout/types';

const props = defineProps<CheckoutSandboxProps>();

const submittingOutcome = ref<'paid' | 'failed' | 'expired' | null>(null);

function simulateOutcome(outcome: 'paid' | 'failed' | 'expired'): void {
    submittingOutcome.value = outcome;
    router.post(
        checkoutSandboxSimulate.url(props.order.id),
        { outcome },
        {
            onFinish: () => {
                submittingOutcome.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="`PayMongo Sandbox — ${order.order_number}`" />

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
            <Alert class="mb-6 rounded-xl border-primary/30 bg-primary/5">
                <ShieldCheck class="size-4 text-primary" aria-hidden="true" />
                <AlertTitle>Local PayMongo Checkout Sandbox</AlertTitle>
                <AlertDescription>
                    No live `PAYMONGO_SECRET_KEY` is configured in this
                    non-production environment. Choose a webhook outcome to
                    verify end-to-end payment reconciliation and stock release.
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <CardTitle class="text-xl">
                                Order {{ order.order_number }}
                            </CardTitle>
                            <CardDescription>
                                Placed {{ order.placed_at_diff }} ·
                                {{ order.shipping_recipient_name }}
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">
                            {{ order.payment_status_label }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div
                        class="flex items-center justify-between rounded-xl border border-border bg-secondary/40 px-4 py-3 text-sm"
                    >
                        <span class="text-muted-foreground"
                            >Amount to Authorize</span
                        >
                        <span
                            class="text-xl font-extrabold text-primary tabular-nums"
                        >
                            {{ order.total_formatted }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <Button
                            type="button"
                            class="h-11 gap-2"
                            :disabled="submittingOutcome !== null"
                            @click="simulateOutcome('paid')"
                        >
                            <CheckCircle2 class="size-4" aria-hidden="true" />
                            <span>Simulate Paid</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 gap-2 text-destructive hover:text-destructive"
                            :disabled="submittingOutcome !== null"
                            @click="simulateOutcome('failed')"
                        >
                            <XCircle class="size-4" aria-hidden="true" />
                            <span>Simulate Failed</span>
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            class="h-11 gap-2"
                            :disabled="submittingOutcome !== null"
                            @click="simulateOutcome('expired')"
                        >
                            <Clock class="size-4" aria-hidden="true" />
                            <span>Simulate Expired</span>
                        </Button>
                    </div>

                    <div class="flex justify-end">
                        <Button as-child variant="ghost" class="h-10">
                            <Link :href="orderShow(order.id)"
                                >View Order Details</Link
                            >
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </StorefrontLayout>
</template>
