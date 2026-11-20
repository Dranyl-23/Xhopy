<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';

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
        class="flex h-11 w-full items-center gap-1 rounded-full border border-input bg-secondary/50 pr-1.5 pl-3.5 transition-colors focus-within:border-ring focus-within:bg-background focus-within:ring-2 focus-within:ring-ring/30"
        @submit.prevent="handleSubmit"
    >
        <label :for="inputId" class="sr-only">Search catalog</label>
        <Search
            class="size-4 shrink-0 text-muted-foreground"
            aria-hidden="true"
        />
        <Input
            :id="inputId"
            v-model="query"
            type="search"
            name="q"
            :placeholder="placeholder"
            autocomplete="off"
            class="h-9 min-w-0 flex-1 border-0 bg-transparent px-2 text-sm shadow-none focus-visible:ring-0 [&::-webkit-search-cancel-button]:appearance-none"
        />
        <button
            v-if="query.length > 0"
            type="button"
            class="inline-flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-secondary hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label="Clear search query"
            @click="clearSearch"
        >
            <X class="size-4" aria-hidden="true" />
        </button>
        <Button
            type="submit"
            size="sm"
            class="h-8 shrink-0 rounded-full px-3.5 text-xs font-semibold"
        >
            Search
        </Button>
    </form>
</template>
