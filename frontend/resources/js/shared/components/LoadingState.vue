<script setup lang="ts">
import { Skeleton } from '@shared/components/ui/skeleton';

withDefaults(
    defineProps<{
        count?: number;
        label?: string;
    }>(),
    {
        count: 4,
        label: 'Loading catalog items...',
    },
);
</script>

<template>
    <div role="status" :aria-label="label" aria-busy="true">
        <span class="sr-only">{{ label }}</span>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="index in count"
                :key="index"
                class="flex flex-col overflow-hidden rounded-xl border border-border bg-card p-3"
            >
                <Skeleton class="aspect-square w-full rounded-lg" />
                <Skeleton class="mt-3 h-4 w-1/3" />
                <Skeleton class="mt-2 h-5 w-3/4" />
                <Skeleton class="mt-4 h-10 w-full rounded-lg" />
            </div>
        </div>
    </div>
</template>
