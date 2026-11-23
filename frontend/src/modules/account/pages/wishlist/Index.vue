<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Heart,
    ImageOff,
    ShoppingBag,
    ShoppingCart,
    Trash2,
} from '@lucide/vue';
import { PriceDisplay } from '@modules/catalog';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { dashboard } from '@/routes';
import { store as cartItemsStore } from '@/routes/cart/items';
import { index as catalogIndex } from '@/routes/catalog';
import {
    destroy as destroyWishlistItem,
    index as wishlistIndex,
} from '@/routes/wishlist';
import type { ProductSummary } from '@modules/catalog';

interface WishlistEntry {
    id: number;
    product_id: number;
    added_at_diff: string;
    product: ProductSummary;
}

defineProps<{
    items: WishlistEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My Account', href: dashboard() },
            { title: 'Wishlist', href: wishlistIndex() },
        ],
    },
});

function addToCart(productId: number | string): void {
    router.post(
        cartItemsStore.url(),
        { product_id: Number(productId), quantity: 1 },
        { preserveScroll: true },
    );
}

function removeItem(wishlistItemId: number): void {
    router.delete(destroyWishlistItem.url(wishlistItemId), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Saved Wishlist — XHopy" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <h1
                    class="flex items-center gap-2 text-2xl font-bold tracking-tight"
                >
                    <Heart class="size-6 text-primary" aria-hidden="true" />
                    <span>Saved Wishlist ({{ items.length }})</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Keep track of products you want to compare or move straight
                    into your shopping cart.
                </p>
            </div>
            <Button as-child variant="outline" class="h-10 gap-2">
                <Link :href="catalogIndex()">
                    <ShoppingBag class="size-4" aria-hidden="true" />
                    <span>Browse Catalog</span>
                </Link>
            </Button>
        </div>

        <div
            v-if="items.length > 0"
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Card
                v-for="item in items"
                :key="item.id"
                class="flex flex-col overflow-hidden"
            >
                <div
                    class="relative aspect-video w-full overflow-hidden bg-secondary/60"
                >
                    <Link
                        v-if="item.product.url"
                        :href="item.product.url"
                        class="block size-full"
                    >
                        <img
                            v-if="item.product.imageUrl"
                            :src="item.product.imageUrl"
                            :alt="item.product.imageAlt"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center text-muted-foreground"
                        >
                            <ImageOff
                                class="size-8 opacity-50"
                                aria-hidden="true"
                            />
                        </div>
                    </Link>
                    <div class="absolute top-2.5 left-2.5">
                        <Badge
                            :variant="
                                item.product.inStock ? 'secondary' : 'outline'
                            "
                        >
                            {{ item.product.stockLabel }}
                        </Badge>
                    </div>
                </div>

                <CardHeader class="flex-1 pb-2">
                    <CardDescription class="text-xs">
                        {{ item.product.categoryName ?? 'Catalog Item' }} ·
                        Saved
                        {{ item.added_at_diff }}
                    </CardDescription>
                    <CardTitle class="line-clamp-2 text-base">
                        <Link
                            v-if="item.product.url"
                            :href="item.product.url"
                            class="hover:text-primary"
                        >
                            {{ item.product.name }}
                        </Link>
                        <template v-else>{{ item.product.name }}</template>
                    </CardTitle>
                </CardHeader>

                <CardContent class="space-y-3 pt-0">
                    <PriceDisplay
                        :price-formatted="item.product.priceFormatted"
                        :original-price-formatted="
                            item.product.originalPriceFormatted
                        "
                        :discount-label="item.product.discountLabel"
                    />

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            class="h-10 flex-1 gap-1.5"
                            :disabled="!item.product.inStock"
                            @click="addToCart(item.product.id)"
                        >
                            <ShoppingCart class="size-4" aria-hidden="true" />
                            <span>{{
                                item.product.inStock
                                    ? 'Add to Cart'
                                    : 'Out of Stock'
                            }}</span>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-10 text-destructive hover:text-destructive"
                            :aria-label="`Remove ${item.product.name} from wishlist`"
                            @click="removeItem(item.id)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <EmptyState
            v-else
            :icon="Heart"
            title="Your wishlist is empty"
            description="Tap the heart icon on any product in the XHopy catalog to save items here for quick checkout later."
            action-label="Explore Catalog"
            @action="router.get(catalogIndex.url())"
        />
    </div>
</template>
