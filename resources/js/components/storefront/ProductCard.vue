<script setup lang="ts">
import { Check, ImageOff, ShoppingCart } from '@lucide/vue';
import { ref } from 'vue';
import PriceDisplay from '@/components/storefront/PriceDisplay.vue';
import RatingDisplay from '@/components/storefront/RatingDisplay.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ProductSummary } from '@/types/storefront';

const props = defineProps<{
    product: ProductSummary;
}>();

const emit = defineEmits<{
    addToCart: [product: ProductSummary];
}>();

const isAdding = ref(false);
const justAdded = ref(false);

function handleAddToCart(): void {
    if (!props.product.inStock || isAdding.value) {
        return;
    }

    isAdding.value = true;
    emit('addToCart', props.product);

    window.setTimeout(() => {
        isAdding.value = false;
        justAdded.value = true;
        window.setTimeout(() => {
            justAdded.value = false;
        }, 1400);
    }, 180);
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
                role="img"
                :aria-label="product.imageAlt"
            >
                <ImageOff class="size-7 opacity-60" aria-hidden="true" />
                <span class="text-xs font-medium">{{ product.name }}</span>
            </div>

            <!-- Stock availability badge (server-authoritative) -->
            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
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
            </div>
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
                    {{ product.name }}
                </h3>

                <p
                    v-if="product.sellerName"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Sold by {{ product.sellerName }}
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
            </div>
        </div>
    </article>
</template>
