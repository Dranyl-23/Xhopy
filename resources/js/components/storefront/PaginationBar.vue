<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types/storefront';

defineProps<{
    meta: PaginationMeta;
}>();

const emit = defineEmits<{
    changePage: [page: number];
}>();
</script>

<template>
    <nav
        v-if="meta.lastPage > 1"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-between gap-4 border-t border-border pt-6"
    >
        <p class="text-sm text-muted-foreground">
            Page
            <span class="font-medium text-foreground">{{
                meta.currentPage
            }}</span>
            of
            <span class="font-medium text-foreground">{{ meta.lastPage }}</span>
            ({{ meta.total }} items)
        </p>

        <div class="flex items-center gap-2">
            <Button
                type="button"
                variant="outline"
                class="h-11 gap-1.5 px-3.5"
                :disabled="meta.currentPage <= 1"
                aria-label="Previous page"
                @click="emit('changePage', meta.currentPage - 1)"
            >
                <ChevronLeft class="size-4" aria-hidden="true" />
                <span>Previous</span>
            </Button>

            <Button
                type="button"
                variant="outline"
                class="h-11 gap-1.5 px-3.5"
                :disabled="meta.currentPage >= meta.lastPage"
                aria-label="Next page"
                @click="emit('changePage', meta.currentPage + 1)"
            >
                <span>Next</span>
                <ChevronRight class="size-4" aria-hidden="true" />
            </Button>
        </div>
    </nav>
</template>
