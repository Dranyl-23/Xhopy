<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = withDefaults(
    defineProps<{
        initialQuery?: string;
        placeholder?: string;
        inputId?: string;
    }>(),
    {
        initialQuery: '',
        placeholder: 'Search products, categories, or SKUs...',
        inputId: 'storefront-search-input',
    },
);

const emit = defineEmits<{
    search: [query: string];
}>();

const query = ref(props.initialQuery);

watch(
    () => props.initialQuery,
    (next) => {
        query.value = next;
    },
);

function handleSubmit(): void {
    emit('search', query.value.trim());
}

function clearSearch(): void {
    query.value = '';
    emit('search', '');
}
</script>

<template>
    <form
        role="search"
        aria-label="Catalog search"
        class="relative flex w-full items-center"
        @submit.prevent="handleSubmit"
    >
        <label :for="inputId" class="sr-only">Search catalog</label>
        <Search
            class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground"
            aria-hidden="true"
        />
        <Input
            :id="inputId"
            v-model="query"
            type="search"
            name="q"
            :placeholder="placeholder"
            autocomplete="off"
            class="h-11 w-full rounded-full border-input bg-secondary/50 pr-24 pl-10 text-sm transition-colors focus-visible:bg-background"
        />
        <button
            v-if="query.length > 0"
            type="button"
            class="absolute right-14 inline-flex size-8 items-center justify-center rounded-full text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label="Clear search query"
            @click="clearSearch"
        >
            <X class="size-4" aria-hidden="true" />
        </button>
        <Button
            type="submit"
            size="sm"
            class="absolute right-1.5 h-8 rounded-full px-3.5 text-xs font-semibold"
        >
            Search
        </Button>
    </form>
</template>
