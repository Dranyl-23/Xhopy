<script setup lang="ts">
import { AlertCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        title?: string;
        message: string;
        retryLabel?: string | null;
    }>(),
    {
        title: 'Unable to load content',
        retryLabel: 'Try again',
    },
);

const emit = defineEmits<{
    retry: [];
}>();
</script>

<template>
    <div
        role="alert"
        class="flex flex-col items-center justify-center rounded-xl border border-destructive/30 bg-destructive/5 px-6 py-10 text-center"
    >
        <div
            class="mb-3 flex size-11 items-center justify-center rounded-full bg-destructive/15 text-destructive"
        >
            <AlertCircle class="size-5" aria-hidden="true" />
        </div>
        <h3 class="text-base font-semibold text-foreground">
            {{ title }}
        </h3>
        <p class="mt-1 max-w-md text-sm text-muted-foreground">
            {{ message }}
        </p>
        <Button
            v-if="retryLabel"
            type="button"
            variant="outline"
            class="mt-4 h-11 px-5"
            @click="emit('retry')"
        >
            {{ retryLabel }}
        </Button>
    </div>
</template>
