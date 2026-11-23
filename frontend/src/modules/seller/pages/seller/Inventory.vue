<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    Download,
    History,
    Package,
    Search,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { PaginationBar } from '@modules/catalog';
import InputError from '@shared/components/InputError.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField } from '@shared/components/ui/select';
import {
    adjust as sellerInventoryAdjust,
    importMethod as sellerInventoryImport,
    index as sellerInventoryIndex,
    template as sellerInventoryTemplate,
} from '@/routes/seller/inventory';
import { index as sellerProductsIndex } from '@/routes/seller/products';
import type {
    SellerInventorySkuRow,
    SellerStockMovementRow,
} from '@modules/seller/types';

const props = defineProps<{
    store: {
        id: number;
        name: string;
        slug: string;
    };
    skus: {
        data: SellerInventorySkuRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    movements: SellerStockMovementRow[];
    summary: {
        totalSkus: number;
        lowStockCount: number;
        outOfStockCount: number;
    };
    filters: {
        q: string;
        filter: string;
        movement_type: string;
    };
}>();

const searchInput = ref(props.filters.q);
const stockFilter = ref(props.filters.filter || 'all');
const stockFilterOptions = [
    { value: 'all', label: 'All Store SKUs' },
    { value: 'low', label: 'Low Stock (<= 5)' },
    { value: 'out', label: 'Out of Stock (0)' },
];

const adjustingRow = ref<SellerInventorySkuRow | null>(null);
const adjustForm = useForm<{
    product_id: number;
    product_variant_id: number | null;
    stock_quantity: number;
    reference: string;
}>({
    product_id: 0,
    product_variant_id: null,
    stock_quantity: 0,
    reference: '',
});

const csvForm = useForm<{
    csv_file: File | null;
}>({
    csv_file: null,
});
const csvInputKey = ref(0);

const paginationMeta = computed(() => ({
    currentPage: props.skus.current_page,
    lastPage: props.skus.last_page,
    perPage: props.skus.per_page,
    total: props.skus.total,
}));

function applyFilter(pageNumber = 1): void {
    router.get(
        sellerInventoryIndex.url({
            query: {
                q: searchInput.value.trim() || undefined,
                filter:
                    stockFilter.value !== 'all' ? stockFilter.value : undefined,
                page: pageNumber > 1 ? pageNumber : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function startAdjust(row: SellerInventorySkuRow): void {
    adjustingRow.value = row;
    adjustForm.product_id = row.product_id;
    adjustForm.product_variant_id = row.product_variant_id;
    adjustForm.stock_quantity = row.stock_quantity;
    adjustForm.reference = 'Store cycle count update';
    adjustForm.clearErrors();
}

function submitAdjust(): void {
    adjustForm.post(sellerInventoryAdjust.url(), {
        preserveScroll: true,
        onSuccess: () => {
            adjustingRow.value = null;
        },
    });
}

function onCsvSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    csvForm.csv_file = input.files?.[0] ?? null;
    csvForm.clearErrors();
}

function submitCsvImport(): void {
    if (!csvForm.csv_file) {
        return;
    }

    csvForm.post(sellerInventoryImport.url(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            csvForm.reset();
            csvInputKey.value++;
        },
    });
}
</script>

<template>
    <Head :title="`${store.name} — Inventory & CSV`" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                >
                    <Boxes class="size-6 text-primary" aria-hidden="true" />
                    <span>Store Inventory &amp; Stock Ledger</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Scoped exclusively to
                    <span class="font-medium text-foreground">{{
                        store.name
                    }}</span
                    >. Adjust live stock counts atomically without unpublishing
                    approved products.
                </p>
            </div>

            <Button as-child variant="outline" class="h-10 gap-1.5">
                <Link :href="sellerProductsIndex()">
                    <Package class="size-4" aria-hidden="true" />
                    <span>Store Products</span>
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Tracked Store SKUs</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ summary.totalSkus }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Low Stock Active SKUs</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ summary.lowStockCount }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Out of Stock Active SKUs</CardDescription>
                    <CardTitle
                        class="text-2xl font-extrabold text-destructive tabular-nums"
                    >
                        {{ summary.outOfStockCount }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card>
            <CardHeader
                class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
            >
                <div>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Upload
                            class="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <span>Bulk Store CSV Stock Import</span>
                    </CardTitle>
                    <CardDescription>
                        Upload CSV rows (<code>sku,stock_quantity,reason</code>,
                        max 500 rows). Imports validate store ownership per SKU
                        and roll back cleanly if any foreign or invalid SKU is
                        detected.
                    </CardDescription>
                </div>

                <Button
                    as="a"
                    :href="sellerInventoryTemplate.url()"
                    variant="outline"
                    size="sm"
                    class="h-10 gap-2"
                >
                    <Download class="size-4" aria-hidden="true" />
                    <span>Download CSV Template</span>
                </Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <form
                    class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    @submit.prevent="submitCsvImport"
                >
                    <div class="flex-1">
                        <Label for="seller-inventory-csv" class="sr-only">
                            Upload Store Inventory CSV
                        </Label>
                        <input
                            :key="csvInputKey"
                            id="seller-inventory-csv"
                            type="file"
                            accept=".csv,text/csv"
                            class="block h-11 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1 file:text-xs file:font-semibold"
                            @change="onCsvSelected"
                        />
                    </div>
                    <Button
                        type="submit"
                        class="h-11 min-w-[175px] justify-center gap-2"
                        :disabled="csvForm.processing || !csvForm.csv_file"
                    >
                        <Upload class="size-4" aria-hidden="true" />
                        <span>{{
                            csvForm.processing
                                ? 'Importing...'
                                : 'Run Store Import'
                        }}</span>
                    </Button>
                </form>

                <Alert
                    v-if="csvForm.errors.csv_file"
                    variant="destructive"
                    class="rounded-xl"
                >
                    <AlertTriangle class="size-4" aria-hidden="true" />
                    <AlertTitle>CSV Import Rolled Back</AlertTitle>
                    <AlertDescription>{{
                        csvForm.errors.csv_file
                    }}</AlertDescription>
                </Alert>
            </CardContent>
        </Card>

        <Card v-if="adjustingRow" class="border-primary/40 bg-primary/5">
            <CardHeader>
                <CardTitle class="text-base">
                    Adjust Stock: {{ adjustingRow.name }}
                    <span v-if="adjustingRow.variant_title">
                        — {{ adjustingRow.variant_title }}
                    </span>
                </CardTitle>
                <CardDescription>
                    SKU {{ adjustingRow.sku }} · Current quantity:
                    {{ adjustingRow.stock_quantity }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-4 sm:grid-cols-3"
                    @submit.prevent="submitAdjust"
                >
                    <div class="space-y-1.5">
                        <Label for="seller-adjust-qty">New Stock Count</Label>
                        <Input
                            id="seller-adjust-qty"
                            v-model.number="adjustForm.stock_quantity"
                            type="number"
                            min="0"
                            max="100000"
                            class="h-11"
                        />
                        <InputError
                            :message="adjustForm.errors.stock_quantity"
                        />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="seller-adjust-ref"
                            >Reason / Audit Reference</Label
                        >
                        <Input
                            id="seller-adjust-ref"
                            v-model="adjustForm.reference"
                            placeholder="e.g., Restock shipment #12"
                            class="h-11"
                        />
                        <InputError :message="adjustForm.errors.reference" />
                    </div>

                    <div class="flex flex-wrap gap-2 sm:col-span-3">
                        <Button
                            type="submit"
                            class="h-10"
                            :disabled="adjustForm.processing"
                        >
                            Save Adjustment
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10"
                            @click="adjustingRow = null"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <form
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
            @submit.prevent="applyFilter()"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="searchInput"
                    placeholder="Search store SKU, product, or variant..."
                    aria-label="Search store SKU inventory"
                    class="h-11 pl-9"
                />
            </div>

            <SelectField
                v-model="stockFilter"
                :options="stockFilterOptions"
                aria-label="Filter store SKUs by stock health"
                trigger-class="sm:w-[190px]"
                @change="applyFilter()"
            />

            <Button type="submit" variant="outline" class="h-11">
                Filter
            </Button>
        </form>

        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Product / Variant</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Listing Status</th>
                                <th class="px-4 py-3">Stock</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="skus.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No store SKUs matched your inventory filter.
                                </td>
                            </tr>
                            <tr
                                v-for="row in skus.data"
                                :key="row.key"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3.5">
                                    <p class="font-medium text-foreground">
                                        {{ row.name }}
                                    </p>
                                    <p
                                        v-if="row.variant_title"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Variant: {{ row.variant_title }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs">
                                    {{ row.sku }}
                                </td>
                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ row.status_label }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge
                                        :variant="
                                            row.stock_quantity === 0
                                                ? 'destructive'
                                                : row.stock_quantity <= 5
                                                  ? 'secondary'
                                                  : 'outline'
                                        "
                                    >
                                        {{ row.stock_quantity }} in stock
                                    </Badge>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-9"
                                        @click="startAdjust(row)"
                                    >
                                        Adjust Stock
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <PaginationBar
            :meta="paginationMeta"
            @change-page="(nextPage) => applyFilter(nextPage)"
        />

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <History class="size-4 text-primary" aria-hidden="true" />
                    <span>Store Stock Movements Ledger</span>
                </CardTitle>
                <CardDescription>
                    Chronological ledger entries scoped exclusively to
                    {{ store.name }}.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-y border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Item / SKU</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Delta</th>
                                <th class="px-4 py-3">After</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="movements.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-6 text-center text-muted-foreground"
                                >
                                    No stock movements recorded for this store
                                    yet.
                                </td>
                            </tr>
                            <tr
                                v-for="movement in movements"
                                :key="movement.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">
                                        {{ movement.product_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        <span v-if="movement.variant_title">
                                            {{ movement.variant_title }} ·
                                        </span>
                                        {{ movement.sku }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">{{
                                        movement.type_label
                                    }}</Badge>
                                </td>
                                <td
                                    class="px-4 py-3 font-semibold tabular-nums"
                                    :class="
                                        movement.quantity_delta > 0
                                            ? 'text-emerald-600'
                                            : movement.quantity_delta < 0
                                              ? 'text-destructive'
                                              : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        movement.quantity_delta > 0
                                            ? `+${movement.quantity_delta}`
                                            : movement.quantity_delta
                                    }}
                                </td>
                                <td class="px-4 py-3 tabular-nums">
                                    {{ movement.stock_after }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ movement.reference }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ movement.created_at_diff }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
