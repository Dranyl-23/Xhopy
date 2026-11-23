<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminCatalogTabs from '@modules/admin/components/AdminCatalogTabs.vue';
import { PaginationBar } from '@modules/catalog';
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
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import {
    create as createProduct,
    destroy as destroyProduct,
    edit as editProduct,
} from '@/routes/admin/catalog/products';
import type {
    AdminCatalogIndexProps,
    AdminProductRow,
} from '@modules/admin/types';

const props = defineProps<AdminCatalogIndexProps>();

const searchInput = ref(props.filters.q);
const statusInput = ref(props.filters.status);
const statusFilterOptions = [
    { value: 'pending_review', label: 'Pending Review' },
    { value: 'active', label: 'Active' },
    { value: 'rejected', label: 'Changes Requested' },
    { value: 'draft', label: 'Draft' },
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
        adminCatalogIndex.url({
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

function handleDelete(product: AdminProductRow): void {
    if (!window.confirm(`Archive product "${product.name}"?`)) {
        return;
    }

    router.delete(destroyProduct.url(product.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Catalog Management — Admin" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Catalog Management
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage active products, variants, private media uploads,
                    categories, brands, and Daily Interest stories.
                </p>
            </div>

            <Button as-child class="h-11 gap-2 self-start sm:self-auto">
                <Link :href="createProduct()">
                    <Plus class="size-4" aria-hidden="true" />
                    <span>New Product</span>
                </Link>
            </Button>
        </div>

        <AdminCatalogTabs active="products" />

        <!-- Summary Metrics -->
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
            <Card
                class="cursor-pointer transition-colors hover:border-primary/50"
                @click="
                    statusInput = 'pending_review';
                    applyFilter();
                "
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Pending Moderation
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold text-primary tabular-nums">
                        {{ stats.pendingReviewProducts ?? 0 }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Active Published
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
                        Categories
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ stats.totalCategories }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-xs font-medium text-muted-foreground"
                    >
                        Brands
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ stats.totalBrands }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
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
                    placeholder="Filter by product name or SKU..."
                    aria-label="Filter products by name or SKU"
                    class="h-11 pl-9"
                />
            </div>

            <SelectField
                v-model="statusInput"
                :options="statusFilterOptions"
                empty-option-label="All Statuses"
                aria-label="Filter by product status"
                trigger-class="sm:w-[175px]"
                @change="applyFilter()"
            />

            <Button type="submit" variant="outline" class="h-11">
                Filter
            </Button>
        </form>

        <!-- Product Table -->
        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Store</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Stock</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="products.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            No products match your filters. Create your first
                            product to populate the catalog.
                        </td>
                    </tr>
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
                                            &middot; {{ item.variantCount }}
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
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    item.isOfficialStore
                                        ? 'secondary'
                                        : 'outline'
                                "
                            >
                                {{ item.storeName ?? 'XHopy Official' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ item.categoryName }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    :variant="
                                        badgeVariantForStatus(item.status)
                                    "
                                >
                                    {{ item.statusLabel }}
                                </Badge>
                                <Badge v-if="item.isFeatured" variant="outline">
                                    Featured
                                </Badge>
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
                                    <Link :href="editProduct(item.id)">
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
