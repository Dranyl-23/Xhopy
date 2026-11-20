<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

withDefaults(
    defineProps<{
        priceFormatted: string;
        originalPriceFormatted?: string | null;
        discountLabel?: string | null;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        originalPriceFormatted: null,
        discountLabel: null,
        size: 'md',
    },
);
</script>

<template>
    <div class="flex flex-wrap items-baseline gap-2">
        <span
            :class="[
                'font-semibold tracking-tight text-foreground',
                size === 'sm' && 'text-sm',
                size === 'md' && 'text-base',
                size === 'lg' && 'text-xl',
            ]"
        >
            {{ priceFormatted }}
        </span>

        <span
            v-if="originalPriceFormatted"
            class="text-xs text-muted-foreground line-through"
        >
            <span class="sr-only">Original price: </span>
            {{ originalPriceFormatted }}
        </span>

        <Badge
            v-if="discountLabel"
            variant="secondary"
            class="bg-brand-subtle px-1.5 py-0 text-[11px] font-semibold text-brand-foreground"
        >
            {{ discountLabel }}
        </Badge>
    </div>
</template>
