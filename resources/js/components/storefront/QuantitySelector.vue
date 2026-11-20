<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        modelValue: number;
        min?: number;
        max?: number;
        disabled?: boolean;
        label?: string;
    }>(),
    {
        min: 1,
        max: 99,
        disabled: false,
        label: 'Quantity',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

function decrement(): void {
    if (!props.disabled && props.modelValue > props.min) {
        emit('update:modelValue', props.modelValue - 1);
    }
}

function increment(): void {
    if (!props.disabled && props.modelValue < props.max) {
        emit('update:modelValue', props.modelValue + 1);
    }
}
</script>

<template>
    <div
        class="inline-flex items-center rounded-md border border-input bg-background"
        role="group"
        :aria-label="label"
    >
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-11 rounded-r-none"
            :disabled="disabled || modelValue <= min"
            :aria-label="`Decrease ${label.toLowerCase()}`"
            @click="decrement"
        >
            <Minus class="size-4" aria-hidden="true" />
        </Button>

        <span
            class="min-w-10 px-2 text-center text-sm font-medium tabular-nums"
            aria-live="polite"
        >
            {{ modelValue }}
        </span>

        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-11 rounded-l-none"
            :disabled="disabled || modelValue >= max"
            :aria-label="`Increase ${label.toLowerCase()}`"
            @click="increment"
        >
            <Plus class="size-4" aria-hidden="true" />
        </Button>
    </div>
</template>
