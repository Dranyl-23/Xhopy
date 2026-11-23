<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, Heart, ImageOff, ShoppingCart } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import PriceDisplay from '@modules/catalog/components/PriceDisplay.vue';
import RatingDisplay from '@modules/catalog/components/RatingDisplay.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { login } from '@/routes';
import { toggle as wishlistToggle } from '@/routes/wishlist';
import type { ProductSummary } from '@modules/catalog/types';

const props = defineProps<{
    product: ProductSummary;
}>();

const emit = defineEmits<{
    addToCart: [product: ProductSummary];
}>();

const page = usePage();
const isWishlisted = computed(() =>
    (page.props.wishlistProductIds ?? []).includes(Number(props.product.id)),
);
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

function handleToggleWishlist(): void {
    if (!isAuthenticated.value) {
        router.visit(login.url());
        return;
    }

    router.post(
        wishlistToggle.url(Number(props.product.id)),
        {},
        { preserveScroll: true },
    );
}

const isAdding = ref(false);
const justAdded = ref(false);
const statusAnnouncement = ref('');

let addTimer: number | undefined;
let resetTimer: number | undefined;

onBeforeUnmount(() => {
    if (addTimer !== undefined) {
        window.clearTimeout(addTimer);
    }
    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }
});

function handleAddToCart(): void {
    if (!props.product.inStock || isAdding.value) {
        return;
    }

    if (addTimer !== undefined) {
        window.clearTimeout(addTimer);
    }
    if (resetTimer !== undefined) {
        window.clearTimeout(resetTimer);
    }

    isAdding.value = true;
    emit('addToCart', props.product);

    addTimer = window.setTimeout(() => {
        isAdding.value = false;
        justAdded.value = true;
        statusAnnouncement.value = `${props.product.name} added to cart.`;

        resetTimer = window.setTimeout(() => {
            justAdded.value = false;
            statusAnnouncement.value = '';
        }, 1600);
    }, 150);
}
</script>

<template>
    <article
        class="group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card transition-shadow hover:shadow-md"
    >
        <!-- Reserved aspect-square container prevents layout shift (CLS) -->
        <div
            class="relative aspect-square w-full overflow-hidden bg-secondary/70"
        >
            <Link
                v-if="product.url"
                :href="product.url"
                :aria-label="`View details for ${product.name}`"
                class="block size-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
            >
                <img
                    v-if="product.imageUrl"
                    :src="product.imageUrl"
                    :alt="product.imageAlt"
                    loading="lazy"
                    decoding="async"
                    width="400"
                    height="400"
                    class="size-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-105"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-muted-foreground"
                    aria-hidden="true"
                >
                    <ImageOff class="size-7 opacity-60" />
                    <span class="line-clamp-2 text-xs font-medium">{{
                        product.name
                    }}</span>
                </div>
            </Link>
            <template v-else>
                <img
                    v-if="product.imageUrl"
                    :src="product.imageUrl"
                    :alt="product.imageAlt"
                    loading="lazy"
                    decoding="async"
                    width="400"
                    height="400"
                    class="size-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-105"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-muted-foreground"
                    aria-hidden="true"
                >
                    <ImageOff class="size-7 opacity-60" />
                    <span class="line-clamp-2 text-xs font-medium">{{
                        product.name
                    }}</span>
                </div>
            </template>

            <!-- Stock availability badge (server-authoritative) -->
            <div
                class="pointer-events-none absolute top-3 left-3 flex flex-wrap gap-1.5"
            >
                <Badge
                    :variant="product.inStock ? 'secondary' : 'outline'"
                    :class="[
                        'px-2 py-0.5 text-[11px] font-medium backdrop-blur-xs',
                        product.inStock
                            ? 'bg-background/90 text-foreground'
                            : 'bg-background/90 text-muted-foreground',
                    ]"
                >
                    {{ product.stockLabel }}
                </Badge>
                <Badge
                    v-if="product.hasVariants"
                    variant="secondary"
                    class="bg-background/90 px-2 py-0.5 text-[11px] font-medium text-foreground backdrop-blur-xs"
                >
                    Options
                </Badge>
            </div>

            <button
                type="button"
                class="absolute top-2.5 right-2.5 z-10 inline-flex size-9 items-center justify-center rounded-full border border-border/70 bg-background/90 text-foreground shadow-xs backdrop-blur-xs transition-colors hover:bg-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="
                    isWishlisted
                        ? `Remove ${product.name} from wishlist`
                        : `Save ${product.name} to wishlist`
                "
                :aria-pressed="isWishlisted"
                @click="handleToggleWishlist"
            >
                <Heart
                    :class="[
                        'size-4',
                        isWishlisted
                            ? 'fill-primary text-primary'
                            : 'text-muted-foreground',
                    ]"
                    aria-hidden="true"
                />
            </button>
        </div>

        <div class="flex flex-1 flex-col justify-between p-4">
            <div>
                <div
                    class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                >
                    <span v-if="product.categoryName" class="truncate">
                        {{ product.categoryName }}
                    </span>
                    <RatingDisplay
                        :rating-average="product.ratingAverage"
                        :rating-count="product.ratingCount"
                    />
                </div>

                <h3
                    class="mt-1.5 line-clamp-2 text-sm leading-snug font-semibold text-foreground"
                >
                    <Link
                        v-if="product.url"
                        :href="product.url"
                        class="rounded-xs hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {{ product.name }}
                    </Link>
                    <template v-else>{{ product.name }}</template>
                </h3>

                <p
                    v-if="product.sellerName"
                    class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span>Sold by</span>
                    <Link
                        v-if="product.storeUrl"
                        :href="product.storeUrl"
                        class="font-medium text-foreground/80 underline-offset-2 transition-colors hover:text-primary hover:underline"
                    >
                        {{ product.sellerName }}
                    </Link>
                    <span v-else>{{ product.sellerName }}</span>
                    <span
                        v-if="product.isOfficialStore"
                        class="inline-flex items-center rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-primary uppercase"
                    >
                        Official
                    </span>
                </p>
            </div>

            <div class="mt-4 space-y-3 border-t border-border/60 pt-3">
                <PriceDisplay
                    :price-formatted="product.priceFormatted"
                    :original-price-formatted="product.originalPriceFormatted"
                    :discount-label="product.discountLabel"
                />

                <Button
                    type="button"
                    class="h-11 w-full gap-2 text-xs font-semibold"
                    :variant="product.inStock ? 'default' : 'secondary'"
                    :disabled="!product.inStock || isAdding"
                    :aria-label="
                        product.inStock
                            ? `Add ${product.name} to cart`
                            : `${product.name} is out of stock`
                    "
                    @click="handleAddToCart"
                >
                    <Check v-if="justAdded" class="size-4" aria-hidden="true" />
                    <ShoppingCart v-else class="size-4" aria-hidden="true" />
                    <span>
                        {{
                            !product.inStock
                                ? 'Out of Stock'
                                : justAdded
                                  ? 'Added to Cart'
                                  : 'Add to Cart'
                        }}
                    </span>
                </Button>

                <span class="sr-only" aria-live="polite">
                    {{ statusAnnouncement }}
                </span>
            </div>
        </div>
    </article>
</template>
