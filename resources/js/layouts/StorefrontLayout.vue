<script setup lang="ts">
import SiteFooter from '@/components/storefront/SiteFooter.vue';
import SiteHeader from '@/components/storefront/SiteHeader.vue';
import type {
    AnnouncementBanner,
    CartSummary,
    CategorySummary,
} from '@/types/storefront';

withDefaults(
    defineProps<{
        announcement?: AnnouncementBanner | null;
        categories?: CategorySummary[];
        cart: CartSummary;
        searchQuery?: string;
        selectedCategory?: string | null;
    }>(),
    {
        announcement: null,
        categories: () => [],
        searchQuery: '',
        selectedCategory: null,
    },
);

const emit = defineEmits<{
    search: [query: string];
    selectCategory: [slug: string | null];
    openCart: [];
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <SiteHeader
            :announcement="announcement"
            :categories="categories"
            :cart="cart"
            :search-query="searchQuery"
            :selected-category="selectedCategory"
            @search="emit('search', $event)"
            @select-category="emit('selectCategory', $event)"
            @open-cart="emit('openCart')"
        />

        <main id="main-content" tabindex="-1" class="flex-1 focus:outline-none">
            <slot />
        </main>

        <SiteFooter />
    </div>
</template>
