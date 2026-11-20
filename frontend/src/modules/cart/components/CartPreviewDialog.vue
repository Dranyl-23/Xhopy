<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Package, ShoppingBag } from '@lucide/vue';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import { index as cartIndex } from '@/routes/cart';
import { index as checkoutIndex } from '@/routes/checkout';
import type { CartSummary } from '@modules/cart/types';

withDefaults(
    defineProps<{
        open: boolean;
        cart: CartSummary;
        lastAddedName?: string | null;
        lastAddedPriceFormatted?: string | null;
        isDemoPreview?: boolean;
    }>(),
    {
        lastAddedName: null,
        lastAddedPriceFormatted: null,
        isDemoPreview: false,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ShoppingBag
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />
                    <span>Shopping Cart</span>
                </DialogTitle>
                <DialogDescription>
                    Review the items currently in your cart or proceed directly
                    to checkout.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3 py-2">
                <template v-if="cart.items && cart.items.length > 0">
                    <ul class="max-h-64 space-y-2.5 overflow-y-auto pr-1">
                        <li
                            v-for="item in cart.items"
                            :key="item.id"
                            class="flex items-center gap-3 rounded-xl border border-border bg-secondary/30 p-2.5 text-sm"
                        >
                            <div
                                class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-background"
                            >
                                <img
                                    v-if="item.imageUrl"
                                    :src="item.imageUrl"
                                    :alt="item.imageAlt"
                                    class="size-full object-cover"
                                />
                                <Package
                                    v-else
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-foreground">
                                    {{ item.productName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="item.variantTitle"
                                        >{{ item.variantTitle }} · </span
                                    >{{ item.quantity }} &times;
                                    {{ item.unitPriceFormatted }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 font-semibold text-foreground tabular-nums"
                            >
                                {{ item.lineTotalFormatted }}
                            </span>
                        </li>
                    </ul>

                    <div
                        class="flex items-center justify-between rounded-xl border border-border bg-card px-3.5 py-2.5 text-sm"
                    >
                        <span class="text-muted-foreground">
                            Subtotal ({{ cart.itemCount }}
                            {{ cart.itemCount === 1 ? 'item' : 'items' }})
                        </span>
                        <span class="font-bold text-foreground tabular-nums">
                            {{ cart.subtotalFormatted ?? '₱0.00' }}
                        </span>
                    </div>
                </template>

                <div
                    v-else-if="cart.itemCount > 0"
                    class="rounded-xl border border-border bg-secondary/40 p-4 text-sm"
                >
                    <p class="font-semibold text-foreground">
                        {{ cart.itemCount }}
                        {{ cart.itemCount === 1 ? 'item' : 'items' }}
                        selected in current session
                    </p>
                    <p
                        v-if="lastAddedName"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Last added: {{ lastAddedName }}
                        <span v-if="lastAddedPriceFormatted"
                            >({{ lastAddedPriceFormatted }})</span
                        >
                    </p>
                </div>

                <p v-else class="py-2 text-sm text-muted-foreground">
                    Your shopping cart is currently empty.
                </p>
            </div>

            <DialogFooter class="flex-col gap-2 sm:flex-row sm:justify-between">
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="emit('update:open', false)"
                >
                    Continue Shopping
                </Button>

                <div
                    v-if="!isDemoPreview"
                    class="flex flex-wrap items-center justify-end gap-2"
                >
                    <Button
                        as-child
                        variant="secondary"
                        class="h-11"
                        @click="emit('update:open', false)"
                    >
                        <Link :href="cartIndex()">View Cart</Link>
                    </Button>
                    <Button
                        v-if="cart.itemCount > 0"
                        as-child
                        class="h-11"
                        @click="emit('update:open', false)"
                    >
                        <Link :href="checkoutIndex()">Checkout</Link>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
