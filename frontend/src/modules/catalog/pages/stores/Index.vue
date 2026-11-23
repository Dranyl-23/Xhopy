<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    Search,
    ShieldCheck,
    Store as StoreIcon,
    X,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { CartPreviewDialog } from '@modules/cart';
import type { StorefrontStoresIndexProps } from '@modules/catalog/types';
import { StorefrontLayout } from '@modules/storefront';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { home } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import { index as storesIndex } from '@/routes/stores';

const props = defineProps<StorefrontStoresIndexProps>();

const cartDialogOpen = ref(false);
const searchInput = ref(props.filters.q);

watch(
    () => props.filters.q,
    (value) => {
        searchInput.value = value ?? '';
    },
);

function submitStoreSearch(): void {
    const trimmed = searchInput.value.trim();

    router.get(
        storesIndex.url({
            query: {
                q: trimmed || undefined,
            },
        }),
        {},
        { preserveScroll: true },
    );
}

function clearStoreSearch(): void {
    searchInput.value = '';
    router.get(storesIndex.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Verified Stores — XHopy" />

    <StorefrontLayout
        :categories="categories"
        :cart="cartSummary"
        @search="
            (q) =>
                router.get(
                    catalogIndex.url({
                        query: { q: q || undefined },
                    }),
                )
        "
        @select-category="
            (category) =>
                router.get(
                    catalogIndex.url({
                        query: { category: category || undefined },
                    }),
                )
        "
        @open-cart="cartDialogOpen = true"
    >
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <nav
                aria-label="Breadcrumb"
                class="mb-4 flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Link :href="home()" class="hover:text-foreground">Home</Link>
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <span class="font-medium text-foreground">Verified Stores</span>
            </nav>

            <div
                class="flex flex-col justify-between gap-4 border-b border-border pb-6 md:flex-row md:items-end"
            >
                <div class="max-w-2xl">
                    <h1
                        class="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                    >
                        Verified Stores
                    </h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Shop curated storefronts operated by XHopy Official and
                        verified marketplace sellers.
                    </p>
                </div>

                <form
                    class="flex w-full items-center gap-2 md:max-w-sm"
                    @submit.prevent="submitStoreSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="searchInput"
                            type="search"
                            aria-label="Search stores by name"
                            placeholder="Search stores by name..."
                            class="h-11 pr-9 pl-9"
                        />
                        <button
                            v-if="searchInput"
                            type="button"
                            aria-label="Clear store search"
                            class="absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground"
                            @click="clearStoreSearch"
                        >
                            <X class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                    <Button type="submit" class="h-11 shrink-0 px-4">
                        Search
                    </Button>
                </form>
            </div>

            <div
                v-if="filters.q"
                class="mt-4 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
            >
                <Badge
                    variant="secondary"
                    class="gap-1.5 py-1 pr-1.5 pl-3 text-xs"
                >
                    <span>Store search: "{{ filters.q }}"</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-background"
                        aria-label="Reset store search"
                        @click="clearStoreSearch"
                    >
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </Badge>
                <span>
                    {{ stores.length }}
                    {{ stores.length === 1 ? 'store' : 'stores' }} found
                </span>
            </div>

            <div
                v-if="stores.length > 0"
                class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="store in stores"
                    :key="store.id"
                    class="flex flex-col justify-between rounded-2xl border border-border bg-card p-6 transition-shadow hover:shadow-md"
                >
                    <div class="space-y-4">
                        <div class="flex items-start gap-3.5">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-primary/10 text-base font-extrabold text-primary"
                            >
                                <img
                                    v-if="store.logoUrl"
                                    :src="store.logoUrl"
                                    :alt="store.name"
                                    class="size-full object-cover"
                                />
                                <span v-else>{{
                                    store.name.slice(0, 2).toUpperCase()
                                }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="store.url"
                                        class="truncate text-base font-bold text-foreground transition-colors hover:text-primary"
                                    >
                                        {{ store.name }}
                                    </Link>
                                </div>

                                <div
                                    class="mt-1 flex flex-wrap items-center gap-2"
                                >
                                    <Badge
                                        v-if="store.isOfficial"
                                        variant="default"
                                        class="gap-1 px-2 py-0.5 text-[10px] tracking-wide uppercase"
                                    >
                                        <ShieldCheck
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        <span>Official Store</span>
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="secondary"
                                        class="gap-1 px-2 py-0.5 text-[10px] tracking-wide uppercase"
                                    >
                                        <StoreIcon
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        <span>Verified Store</span>
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ store.productCount }}
                                        {{
                                            store.productCount === 1
                                                ? 'product'
                                                : 'products'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p class="line-clamp-2 text-sm text-muted-foreground">
                            {{
                                store.description ??
                                'Approved storefront fulfilling authentic orders on XHopy.'
                            }}
                        </p>
                    </div>

                    <Button
                        as-child
                        variant="outline"
                        class="mt-6 h-10 w-full justify-between font-semibold"
                    >
                        <Link :href="store.url">
                            <span>Visit Storefront</span>
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </article>
            </div>

            <div v-else class="mt-8">
                <EmptyState
                    title="No stores match this search"
                    description="Try clearing the search query or browse the full product catalog."
                >
                    <template #action>
                        <div class="flex flex-wrap justify-center gap-3">
                            <Button type="button" @click="clearStoreSearch">
                                Show all verified stores
                            </Button>
                            <Button as-child variant="outline">
                                <Link :href="catalogIndex()">
                                    Browse Full Catalog
                                </Link>
                            </Button>
                        </div>
                    </template>
                </EmptyState>
            </div>
        </div>

        <CartPreviewDialog v-model:open="cartDialogOpen" :cart="cartSummary" />
    </StorefrontLayout>
</template>
