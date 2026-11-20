<script setup lang="ts">
import { ArrowUpRight, Layers } from '@lucide/vue';
import type { CategorySummary } from '@modules/storefront/types';

defineProps<{
    category: CategorySummary;
    active?: boolean;
}>();

const emit = defineEmits<{
    select: [slug: string];
}>();
</script>

<template>
    <button
        type="button"
        :class="[
            'group flex min-h-28 w-full flex-col justify-between rounded-xl border p-4 text-left transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            active
                ? 'border-primary bg-brand-subtle/60 shadow-xs'
                : 'border-border bg-card hover:border-primary/50 hover:shadow-xs',
        ]"
        :aria-pressed="active"
        @click="emit('select', category.slug)"
    >
        <div class="flex w-full items-start justify-between gap-2">
            <span
                class="inline-flex size-9 items-center justify-center rounded-lg bg-brand-subtle text-brand-foreground"
            >
                <Layers class="size-4" aria-hidden="true" />
            </span>
            <ArrowUpRight
                class="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary"
                aria-hidden="true"
            />
        </div>

        <div class="mt-4">
            <h3
                class="line-clamp-1 text-sm font-semibold text-foreground group-hover:text-primary"
            >
                {{ category.name }}
            </h3>
            <p
                v-if="category.description"
                class="mt-0.5 line-clamp-1 text-xs text-muted-foreground"
            >
                {{ category.description }}
            </p>
            <p
                v-if="typeof category.productCount === 'number'"
                class="mt-1 text-xs font-medium text-muted-foreground"
            >
                {{ category.productCount }}
                {{ category.productCount === 1 ? 'product' : 'products' }}
            </p>
        </div>
    </button>
</template>
