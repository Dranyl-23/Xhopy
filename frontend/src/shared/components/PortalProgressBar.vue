<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const active = ref(false);
let removeStart: (() => void) | null = null;
let removeFinish: (() => void) | null = null;

onMounted(() => {
    removeStart = router.on('start', () => {
        active.value = true;
    });
    removeFinish = router.on('finish', () => {
        active.value = false;
    });
});

onBeforeUnmount(() => {
    removeStart?.();
    removeFinish?.();
});
</script>

<template>
    <div
        v-show="active"
        role="progressbar"
        aria-label="Loading page"
        aria-valuetext="Loading"
        class="pointer-events-none fixed inset-x-0 top-0 z-50 h-0.5 overflow-hidden bg-primary/20"
    >
        <div
            class="h-full w-1/2 animate-pulse bg-primary transition-all duration-300"
        />
    </div>
</template>
