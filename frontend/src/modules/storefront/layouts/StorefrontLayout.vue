<script setup lang="ts">
import SiteFooter from '@modules/storefront/components/SiteFooter.vue';
import SiteHeader from '@modules/storefront/components/SiteHeader.vue';
import PortalProgressBar from '@shared/components/PortalProgressBar.vue';
import type { CartSummary } from '@modules/cart';
import type {
    AnnouncementBanner,
    CategorySummary,
} from '@modules/storefront/types';

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
        <PortalProgressBar />
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
