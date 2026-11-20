<script setup lang="ts">
import { PackageOpen } from '@lucide/vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        actionLabel?: string | null;
        icon?: Component;
    }>(),
    {
        actionLabel: null,
        icon: undefined,
    },
);

const emit = defineEmits<{
    action: [];
}>();
</script>

<template>
    <div
        role="status"
        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/60 px-6 py-12 text-center"
    >
        <div
            class="mb-4 flex size-12 items-center justify-center rounded-full bg-brand-subtle text-brand-foreground"
        >
            <component
                :is="icon ?? PackageOpen"
                class="size-6"
                aria-hidden="true"
            />
        </div>
        <h3 class="text-base font-semibold text-foreground">
            {{ title }}
        </h3>
        <p class="mt-1.5 max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div v-if="actionLabel || $slots.actions" class="mt-5">
            <slot name="actions">
                <Button
                    v-if="actionLabel"
                    type="button"
                    variant="outline"
                    class="h-11 px-5"
                    @click="emit('action')"
                >
                    {{ actionLabel }}
                </Button>
            </slot>
        </div>
    </div>
</template>
