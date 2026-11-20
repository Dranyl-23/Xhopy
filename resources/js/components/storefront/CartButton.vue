<script setup lang="ts">
import { ShoppingBag } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { CartSummary } from '@/types/storefront';

defineProps<{
    cart: CartSummary;
}>();

const emit = defineEmits<{
    openCart: [];
}>();
</script>

<template>
    <Button
        type="button"
        variant="outline"
        class="relative h-11 min-w-11 gap-2 rounded-full px-3.5"
        :aria-label="`Shopping cart, ${cart.itemCount} ${cart.itemCount === 1 ? 'item' : 'items'}`"
        @click="emit('openCart')"
    >
        <ShoppingBag class="size-4 text-foreground" aria-hidden="true" />
        <span class="hidden text-sm font-medium sm:inline">Cart</span>
        <span
            class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-xs font-semibold text-primary-foreground tabular-nums"
            aria-live="polite"
        >
            {{ cart.itemCount }}
        </span>
    </Button>
</template>
