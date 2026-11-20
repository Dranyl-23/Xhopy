<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField, type SelectOption } from '@shared/components/ui/select';
import { Separator } from '@shared/components/ui/separator';
import type {
    BrandSummary,
    CatalogFilters,
    CategorySummary,
} from '@modules/catalog/types';

const props = defineProps<{
    categories: CategorySummary[];
    brands: BrandSummary[];
    filters: CatalogFilters;
    idPrefix?: string;
}>();

const emit = defineEmits<{
    apply: [partial: Partial<CatalogFilters>];
    reset: [];
}>();

const minPriceInput = ref(props.filters.min_price);
const maxPriceInput = ref(props.filters.max_price);
const brandOptions = computed<SelectOption[]>(() =>
    props.brands.map((brand) => ({
        value: brand.slug,
        label: brand.name,
    })),
);

watch(
    () => [props.filters.min_price, props.filters.max_price],
    ([minVal, maxVal]) => {
        minPriceInput.value = minVal ?? '';
        maxPriceInput.value = maxVal ?? '';
    },
);

function applyPriceRange(): void {
    emit('apply', {
        min_price: minPriceInput.value.trim(),
        max_price: maxPriceInput.value.trim(),
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2
                class="text-sm font-bold tracking-wide text-foreground uppercase"
            >
                Filters
            </h2>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-8 px-2 text-xs font-semibold text-primary"
                @click="emit('reset')"
            >
                Reset all
            </Button>
        </div>

        <!-- Categories -->
        <div class="space-y-2.5">
            <h3
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Categories
            </h3>
            <ul class="space-y-1">
                <li>
                    <button
                        type="button"
                        :class="[
                            'flex min-h-10 w-full items-center justify-between rounded-lg px-3 text-left text-sm transition-colors',
                            !filters.category
                                ? 'bg-primary/10 font-semibold text-primary'
                                : 'text-foreground hover:bg-secondary',
                        ]"
                        @click="emit('apply', { category: null })"
                    >
                        <span>All Categories</span>
                    </button>
                </li>
                <li v-for="category in categories" :key="category.id">
                    <button
                        type="button"
                        :class="[
                            'flex min-h-10 w-full items-center justify-between gap-2 rounded-lg px-3 text-left text-sm transition-colors',
                            filters.category === category.slug
                                ? 'bg-primary/10 font-semibold text-primary'
                                : 'text-foreground hover:bg-secondary',
                        ]"
                        @click="emit('apply', { category: category.slug })"
                    >
                        <span class="truncate">{{ category.name }}</span>
                        <span
                            v-if="
                                category.productCount !== undefined &&
                                category.productCount !== null
                            "
                            class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >
                            {{ category.productCount }}
                        </span>
                    </button>
                </li>
            </ul>
        </div>

        <Separator />

        <!-- Brands -->
        <div v-if="brands.length > 0" class="space-y-2.5">
            <Label
                :for="`${idPrefix ?? 'desktop'}-brand-filter`"
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Brand
            </Label>
            <SelectField
                :id="`${idPrefix ?? 'desktop'}-brand-filter`"
                :model-value="filters.brand ?? ''"
                :options="brandOptions"
                empty-option-label="All Brands"
                @change="
                    (value) =>
                        emit('apply', {
                            brand: value === '' ? null : String(value),
                        })
                "
            />
        </div>

        <Separator v-if="brands.length > 0" />

        <!-- Price Range -->
        <form class="space-y-3" @submit.prevent="applyPriceRange">
            <h3
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Price Range (PHP)
            </h3>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <Label
                        :for="`${idPrefix ?? 'desktop'}-min-price`"
                        class="text-xs text-muted-foreground"
                    >
                        Min (₱)
                    </Label>
                    <Input
                        :id="`${idPrefix ?? 'desktop'}-min-price`"
                        v-model="minPriceInput"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="0"
                        class="h-10"
                    />
                </div>
                <div class="space-y-1">
                    <Label
                        :for="`${idPrefix ?? 'desktop'}-max-price`"
                        class="text-xs text-muted-foreground"
                    >
                        Max (₱)
                    </Label>
                    <Input
                        :id="`${idPrefix ?? 'desktop'}-max-price`"
                        v-model="maxPriceInput"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="Any"
                        class="h-10"
                    />
                </div>
            </div>
            <Button
                type="submit"
                variant="outline"
                class="h-10 w-full text-xs font-semibold"
            >
                Apply Price Range
            </Button>
        </form>

        <Separator />

        <!-- Availability -->
        <div class="flex items-center gap-3">
            <Checkbox
                :id="`${idPrefix ?? 'desktop'}-in-stock`"
                :model-value="filters.in_stock"
                @update:model-value="
                    emit('apply', { in_stock: $event === true })
                "
            />
            <Label
                :for="`${idPrefix ?? 'desktop'}-in-stock`"
                class="cursor-pointer text-sm font-medium text-foreground"
            >
                In stock only
            </Label>
        </div>
    </div>
</template>
