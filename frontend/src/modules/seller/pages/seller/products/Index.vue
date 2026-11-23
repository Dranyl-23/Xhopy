<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    ImageOff,
    Package,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import { PaginationBar } from '@modules/catalog';
import EmptyState from '@shared/components/EmptyState.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { SelectField } from '@shared/components/ui/select';
import { index as sellerInventoryIndex } from '@/routes/seller/inventory';
import {
    create as createSellerProduct,
    destroy as destroySellerProduct,
    edit as editSellerProduct,
    index as sellerProductsIndex,
} from '@/routes/seller/products';
import type {
    SellerProductIndexProps,
    SellerProductRow,
} from '@modules/seller/types';

const props = defineProps<SellerProductIndexProps>();

const searchInput = ref(props.filters.q);
const statusInput = ref(props.filters.status);

const statusFilterOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'pending_review', label: 'Pending Review' },
    { value: 'active', label: 'Active' },
    { value: 'rejected', label: 'Changes Requested' },
    { value: 'archived', label: 'Archived' },
] as const;

function badgeVariantForStatus(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'active') return 'default';
    if (status === 'rejected') return 'destructive';
    if (status === 'pending_review') return 'outline';
    return 'secondary';
}

function applyFilter(page = 1): void {
    router.get(
        sellerProductsIndex.url({
            query: {
                q: searchInput.value.trim() || undefined,
                status: statusInput.value || undefined,
                page: page > 1 ? page : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function handleDelete(product: SellerProductRow): void {
    if (!window.confirm(`Archive "${product.name}"?`)) {
        return;
    }

    router.delete(destroySellerProduct.url(product.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${store.name} — Products`" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Store Products
                </h1>
                <p class="text-sm text-muted-foreground">
                    Create, organize, and submit listings for
                    <span class="font-medium text-foreground">{{
                        store.name
                    }}</span>
                    for Admin moderation before going live.
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <Button as-child variant="outline" class="h-11 gap-2">
                    <Link :href="sellerInventoryIndex()">
                        <Boxes class="size-4" aria-hidden="true" />
                        <span>Inventory &amp; CSV</span>
                    </Link>
                </Button>
                <Button as-child class="h-11 gap-2">
                    <Link :href="createSellerProduct()">
                        <Plus class="size-4" aria-hidden="true" />
                        <span>New Product</span>
                    </Link>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Total Products
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ stats.totalProducts }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Active Live
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold text-primary tabular-nums">
                        {{ stats.activeProducts }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Pending Review
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ stats.pendingReviewProducts }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Changes Requested
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="
                            stats.rejectedProducts > 0 ? 'text-destructive' : ''
                        "
                    >
                        {{ stats.rejectedProducts }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Drafts
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ stats.draftProducts }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <form
            class="flex flex-col gap-3 sm:flex-row sm:items-center"
            @submit.prevent="applyFilter()"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="searchInput"
                    placeholder="Filter your products by name or SKU..."
                    aria-label="Filter store products"
                    class="h-11 pl-9"
                />
            </div>

            <SelectField
                v-model="statusInput"
                :options="statusFilterOptions"
                empty-option-label="All Statuses"
                aria-label="Filter by moderation status"
                trigger-class="sm:w-[195px]"
                @change="applyFilter()"
            />

            <Button type="submit" variant="outline" class="h-11">
                Filter
            </Button>
        </form>

        <EmptyState
            v-if="products.length === 0"
            title="No store products found"
            description="Add your first product or adjust your active filters to manage listings for your store."
        >
            <template #icon>
                <Package class="size-6" aria-hidden="true" />
            </template>
            <template #action>
                <Button as-child class="h-10 gap-1.5">
                    <Link :href="createSellerProduct()">
                        <Plus class="size-4" aria-hidden="true" />
                        <span>Add First Product</span>
                    </Link>
                </Button>
            </template>
        </EmptyState>

        <div
            v-else
            class="overflow-x-auto rounded-xl border border-border bg-card"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Status &amp; Moderation</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="item in products"
                        :key="item.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-secondary/40"
                                >
                                    <img
                                        v-if="item.imageUrl"
                                        :src="item.imageUrl"
                                        :alt="item.name"
                                        class="size-full object-cover"
                                    />
                                    <ImageOff
                                        v-else
                                        class="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate font-semibold text-foreground"
                                    >
                                        {{ item.name }}
                                    </p>
                                    <p
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ item.sku }}
                                        <span v-if="item.variantCount > 0">
                                            · {{ item.variantCount }}
                                            {{
                                                item.variantCount === 1
                                                    ? 'variant'
                                                    : 'variants'
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ item.categoryName }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="space-y-1">
                                <Badge
                                    :variant="
                                        badgeVariantForStatus(item.status)
                                    "
                                >
                                    {{ item.statusLabel }}
                                </Badge>
                                <p
                                    v-if="
                                        item.status === 'rejected' &&
                                        item.moderationNotes
                                    "
                                    class="flex items-start gap-1 text-xs text-destructive"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="line-clamp-2">{{
                                        item.moderationNotes
                                    }}</span>
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium tabular-nums">
                            {{ item.priceFormatted }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ item.stockQuantity }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <Button
                                    as-child
                                    variant="ghost"
                                    size="sm"
                                    class="h-9 gap-1.5"
                                >
                                    <Link :href="editSellerProduct(item.id)">
                                        <Pencil
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span>Edit</span>
                                    </Link>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-9 text-destructive hover:text-destructive"
                                    :aria-label="`Archive ${item.name}`"
                                    @click="handleDelete(item)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :meta="pagination" @change-page="applyFilter($event)" />
    </div>
</template>
