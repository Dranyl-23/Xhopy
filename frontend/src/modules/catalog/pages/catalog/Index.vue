<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, ShoppingBag, SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { CartPreviewDialog, type CartSummary } from '@modules/cart';
import CatalogFilterPanel from '@modules/catalog/components/CatalogFilterPanel.vue';
import PaginationBar from '@modules/catalog/components/PaginationBar.vue';
import ProductCard from '@modules/catalog/components/ProductCard.vue';
import { StorefrontLayout } from '@modules/storefront';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { SelectField } from '@shared/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@shared/components/ui/sheet';
import { home } from '@/routes';
import { store as cartItemsStore } from '@/routes/cart/items';
import { index as catalogIndex } from '@/routes/catalog';
import type {
    CatalogFilters,
    CatalogIndexProps,
    ProductSummary,
} from '@modules/catalog/types';

const props = defineProps<CatalogIndexProps>();

const perPageOptions = [
    { value: 12, label: '12 / page' },
    { value: 24, label: '24 / page' },
    { value: 48, label: '48 / page' },
] as const;

const localCartCount = ref(props.cartSummary.itemCount);
const cartDialogOpen = ref(false);
const mobileFiltersOpen = ref(false);
const lastSelectedProduct = ref<ProductSummary | null>(null);

const effectiveCart = computed<CartSummary>(() => ({
    itemCount: localCartCount.value,
    subtotalFormatted: props.cartSummary.subtotalFormatted,
    items: props.cartSummary.items,
}));

watch(
    () => props.cartSummary.itemCount,
    (count) => {
        localCartCount.value = count;
    },
);

const activeCategoryName = computed(
    () =>
        props.categories.find((c) => c.slug === props.filters.category)?.name ??
        null,
);

const activeBrandName = computed(
    () =>
        props.brands.find((b) => b.slug === props.filters.brand)?.name ?? null,
);

const activeStoreName = computed(
    () =>
        props.stores?.find((s) => s.slug === props.filters.store)?.name ?? null,
);

const hasActiveFilters = computed(
    () =>
        Boolean(props.filters.q) ||
        Boolean(props.filters.category) ||
        Boolean(props.filters.brand) ||
        Boolean(props.filters.store) ||
        Boolean(props.filters.min_price) ||
        Boolean(props.filters.max_price) ||
        props.filters.in_stock,
);

function navigateWithFilters(
    partial: Partial<CatalogFilters> & { page?: number },
): void {
    const merged = {
        ...props.filters,
        ...partial,
    };

    router.get(
        catalogIndex.url({
            query: {
                q: merged.q || undefined,
                category: merged.category || undefined,
                brand: merged.brand || undefined,
                store: merged.store || undefined,
                min_price: merged.min_price || undefined,
                max_price: merged.max_price || undefined,
                in_stock: merged.in_stock ? '1' : undefined,
                sort:
                    merged.sort && merged.sort !== 'featured'
                        ? merged.sort
                        : undefined,
                per_page: merged.per_page !== 12 ? merged.per_page : undefined,
                page:
                    partial.page && partial.page > 1 ? partial.page : undefined,
            },
        }),
        {},
        { preserveScroll: true },
    );

    mobileFiltersOpen.value = false;
}

function resetFilters(): void {
    router.get(catalogIndex.url());
    mobileFiltersOpen.value = false;
}

function handleAddToCart(product: ProductSummary): void {
    lastSelectedProduct.value = product;

    if (typeof product.id !== 'number') {
        localCartCount.value += 1;
        return;
    }

    router.post(
        cartItemsStore.url(),
        {
            product_id: product.id,
            quantity: 1,
        },
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="Catalog — XHopy" />

    <StorefrontLayout
        :categories="categories"
        :cart="effectiveCart"
        :search-query="filters.q"
        :selected-category="filters.category"
        @search="navigateWithFilters({ q: $event })"
        @select-category="navigateWithFilters({ category: $event })"
        @open-cart="cartDialogOpen = true"
    >
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Breadcrumb & Title -->
            <nav
                aria-label="Breadcrumb"
                class="mb-4 flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Link :href="home()" class="hover:text-foreground">Home</Link>
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <span class="font-medium text-foreground">Catalog</span>
            </nav>

            <div
                class="flex flex-col justify-between gap-4 border-b border-border pb-6 sm:flex-row sm:items-end"
            >
                <div>
                    <h1
                        class="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                    >
                        {{ activeCategoryName ?? 'All Catalog Products' }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ pagination.total }}
                        {{ pagination.total === 1 ? 'item' : 'items' }} matching
                        current filters
                    </p>
                </div>

                <!-- Sort & Per-page Controls + Mobile Filter Drawer -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Sheet v-model:open="mobileFiltersOpen">
                        <SheetTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 gap-2 lg:hidden"
                            >
                                <SlidersHorizontal
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span>Filters</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-80 overflow-y-auto p-6"
                        >
                            <SheetHeader class="mb-4 text-left">
                                <SheetTitle>Refine Catalog</SheetTitle>
                                <SheetDescription>
                                    Filter products by category, brand, price,
                                    and stock status.
                                </SheetDescription>
                            </SheetHeader>
                            <CatalogFilterPanel
                                id-prefix="mobile"
                                :categories="categories"
                                :brands="brands"
                                :stores="stores"
                                :filters="filters"
                                @apply="navigateWithFilters"
                                @reset="resetFilters"
                            />
                        </SheetContent>
                    </Sheet>

                    <div class="flex items-center gap-2">
                        <label
                            for="catalog-sort"
                            class="text-xs font-semibold text-muted-foreground uppercase"
                        >
                            Sort
                        </label>
                        <SelectField
                            id="catalog-sort"
                            :model-value="filters.sort"
                            :options="sortOptions"
                            trigger-class="w-[185px]"
                            @change="
                                (value) =>
                                    navigateWithFilters({
                                        sort: String(value),
                                    })
                            "
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="catalog-per-page" class="sr-only">
                            Items per page
                        </label>
                        <SelectField
                            id="catalog-per-page"
                            :model-value="filters.per_page"
                            :options="perPageOptions"
                            aria-label="Items per page"
                            trigger-class="w-[125px]"
                            @change="
                                (value) =>
                                    navigateWithFilters({
                                        per_page: Number(value),
                                    })
                            "
                        />
                    </div>
                </div>
            </div>

            <!-- Active Filter Chips -->
            <div
                v-if="hasActiveFilters"
                class="mt-4 flex flex-wrap items-center gap-2"
            >
                <Badge
                    v-if="filters.q"
                    variant="secondary"
                    class="gap-1.5 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>Search: "{{ filters.q }}"</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        aria-label="Clear search filter"
                        @click="navigateWithFilters({ q: '' })"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="activeCategoryName"
                    variant="secondary"
                    class="gap-1.5 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>Category: {{ activeCategoryName }}</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        aria-label="Clear category filter"
                        @click="navigateWithFilters({ category: null })"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="activeBrandName"
                    variant="secondary"
                    class="gap-1.5 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>Brand: {{ activeBrandName }}</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        aria-label="Clear brand filter"
                        @click="navigateWithFilters({ brand: null })"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="activeStoreName"
                    variant="secondary"
                    class="gap-1.5 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>Store: {{ activeStoreName }}</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        aria-label="Clear store filter"
                        @click="navigateWithFilters({ store: null })"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </Badge>

                <Badge
                    v-if="filters.in_stock"
                    variant="secondary"
                    class="gap-1.5 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>In Stock Only</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        aria-label="Clear in-stock filter"
                        @click="navigateWithFilters({ in_stock: false })"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </Badge>
            </div>

            <!-- Main Layout: Sidebar Filters + Product Grid -->
            <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[260px_1fr]">
                <aside
                    aria-label="Catalog filters"
                    class="hidden rounded-2xl border border-border bg-card p-5 lg:block lg:self-start"
                >
                    <CatalogFilterPanel
                        id-prefix="desktop"
                        :categories="categories"
                        :brands="brands"
                        :stores="stores"
                        :filters="filters"
                        @apply="navigateWithFilters"
                        @reset="resetFilters"
                    />
                </aside>

                <div class="space-y-8">
                    <div
                        v-if="products.length > 0"
                        class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <ProductCard
                            v-for="product in products"
                            :key="product.id"
                            :product="product"
                            @add-to-cart="handleAddToCart"
                        />
                    </div>

                    <EmptyState
                        v-else
                        :icon="ShoppingBag"
                        title="No matching catalog products"
                        description="No active products matched your current filter combination."
                        :action-label="
                            hasActiveFilters ? 'Reset all filters' : null
                        "
                        @action="resetFilters"
                    />

                    <PaginationBar
                        :meta="pagination"
                        @change-page="navigateWithFilters({ page: $event })"
                    />
                </div>
            </div>
        </div>

        <CartPreviewDialog
            v-model:open="cartDialogOpen"
            :cart="effectiveCart"
            :last-added-name="lastSelectedProduct?.name"
            :last-added-price-formatted="lastSelectedProduct?.priceFormatted"
        />
    </StorefrontLayout>
</template>
