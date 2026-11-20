<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    ratingAverage?: number | null;
    ratingCount?: number | null;
}>();

const hasVerifiedRating = computed(
    () =>
        typeof props.ratingAverage === 'number' &&
        props.ratingAverage > 0 &&
        typeof props.ratingCount === 'number' &&
        props.ratingCount > 0,
);

const formattedAverage = computed(() =>
    typeof props.ratingAverage === 'number'
        ? props.ratingAverage.toFixed(1)
        : '',
);
</script>

<template>
    <div
        v-if="hasVerifiedRating"
        class="inline-flex items-center gap-1 text-xs text-muted-foreground"
        :aria-label="`Rated ${formattedAverage} out of 5 from ${ratingCount} verified reviews`"
    >
        <Star
            class="size-3.5 fill-amber-500 text-amber-500"
            aria-hidden="true"
        />
        <span class="font-medium text-foreground">{{ formattedAverage }}</span>
        <span>({{ ratingCount }})</span>
    </div>
</template>
