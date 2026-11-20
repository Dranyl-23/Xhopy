<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M15.75 14.5V12C15.75 7.444 19.444 3.75 24 3.75C28.556 3.75 32.25 7.444 32.25 12V14.5H28.55V12C28.55 9.487 26.513 7.45 24 7.45C21.487 7.45 19.45 9.487 19.45 12V14.5H15.75ZM11.5 16C10.12 16 8.95 17.01 8.76 18.38L6.08 38.52C5.71 41.32 7.89 43.8 10.71 43.8H37.29C40.11 43.8 42.29 41.32 41.92 38.52L39.24 18.38C39.05 17.01 37.88 16 36.5 16H11.5ZM16.9 22.8H21.25L24 27.15L26.75 22.8H31.1L26.35 29.9L31.45 37.4H27.05L24 32.65L20.95 37.4H16.55L21.65 29.9L16.9 22.8Z"
        />
    </svg>
</template>
