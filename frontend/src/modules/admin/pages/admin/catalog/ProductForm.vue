<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Plus, Star, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminCatalogTabs from '@modules/admin/components/AdminCatalogTabs.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField, type SelectOption } from '@shared/components/ui/select';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import {
    store as storeProduct,
    update as updateProduct,
} from '@/routes/admin/catalog/products';
import {
    destroy as destroyImage,
    primary as setPrimaryImage,
    store as uploadImage,
} from '@/routes/admin/catalog/products/images';
import type { AdminProductFormProps } from '@modules/admin/types';

const props = defineProps<AdminProductFormProps>();

const categoryOptions = computed<SelectOption<number>[]>(() =>
    props.categories.map((cat) => ({
        value: cat.id,
        label: cat.name,
    })),
);

const brandOptions = computed<SelectOption<number>[]>(() =>
    props.brands.map((brand) => ({
        value: brand.id,
        label: brand.name,
    })),
);

const variantOptions = computed<SelectOption<number>[]>(() =>
    (props.product?.variants ?? [])
        .filter(
            (variant): variant is typeof variant & { id: number } =>
                typeof variant.id === 'number',
        )
        .map((variant) => ({
            value: variant.id,
            label: `${variant.title} (${variant.sku})`,
        })),
);

interface SpecRow {
    key: string;
    value: string;
}

const specRows = ref<SpecRow[]>(
    props.product
        ? Object.entries(props.product.specifications ?? {}).map(
              ([key, value]) => ({ key, value }),
          )
        : [],
);

const form = useForm({
    category_id: props.product?.category_id ?? props.categories[0]?.id ?? 0,
    brand_id: props.product?.brand_id ?? ('' as number | ''),
    seller_name: props.product?.seller_name ?? 'XHopy Official',
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    sku: props.product?.sku ?? '',
    short_description: props.product?.short_description ?? '',
    description: props.product?.description ?? '',
    status: props.product?.status ?? 'draft',
    is_featured: props.product?.is_featured ?? false,
    currency: props.product?.currency ?? 'PHP',
    base_price: props.product?.base_price ?? '0.00',
    compare_at_price: props.product?.compare_at_price ?? '',
    stock_quantity: props.product?.stock_quantity ?? 0,
    specifications: {} as Record<string, string>,
    variants: (props.product?.variants ?? []).map((variant) => ({
        ...variant,
    })),
});

const imageForm = useForm({
    image: null as File | null,
    alt_text: props.product?.name ?? '',
    variant_id: '' as number | '',
    is_primary: false,
});

function addSpecRow(): void {
    specRows.value.push({ key: '', value: '' });
}

function removeSpecRow(index: number): void {
    specRows.value.splice(index, 1);
}

function addVariantRow(): void {
    form.variants.push({
        title: '',
        sku: '',
        price: form.base_price || '0.00',
        compare_at_price: '',
        stock_quantity: 0,
        options: {},
        is_default: form.variants.length === 0,
        is_active: true,
    });
}

function removeVariantRow(index: number): void {
    form.variants.splice(index, 1);
}

function submitProduct(): void {
    const specRecord: Record<string, string> = {};
    for (const row of specRows.value) {
        const k = row.key.trim();
        const v = row.value.trim();
        if (k !== '' && v !== '') {
            specRecord[k] = v;
        }
    }

    form.transform((data) => ({
        ...data,
        brand_id: data.brand_id === '' ? null : Number(data.brand_id),
        compare_at_price:
            data.compare_at_price === '' ? null : data.compare_at_price,
        specifications: specRecord,
        variants: data.variants.map((variant) => ({
            ...variant,
            compare_at_price:
                variant.compare_at_price === ''
                    ? null
                    : variant.compare_at_price,
        })),
    }));

    if (props.product) {
        form.patch(updateProduct.url(props.product.id), {
            preserveScroll: true,
        });
    } else {
        form.post(storeProduct.url());
    }
}

function handleFileSelection(event: Event): void {
    const input = event.target as HTMLInputElement;
    imageForm.image = input.files?.[0] ?? null;
}

function submitImageUpload(): void {
    if (!props.product || !imageForm.image) {
        return;
    }

    imageForm
        .transform((data) => ({
            ...data,
            variant_id: data.variant_id === '' ? null : Number(data.variant_id),
        }))
        .post(uploadImage.url(props.product.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                imageForm.reset('image', 'is_primary');
            },
        });
}

function makePrimary(imageId: number): void {
    if (!props.product) {
        return;
    }

    router.patch(
        setPrimaryImage.url({ product: props.product.id, image: imageId }),
        {},
        { preserveScroll: true },
    );
}

function removeImage(imageId: number): void {
    if (!props.product) {
        return;
    }

    router.delete(
        destroyImage.url({ product: props.product.id, image: imageId }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="product ? `Edit ${product.name}` : 'Create Product'" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex items-center justify-between gap-4">
            <Button as-child variant="ghost" size="sm" class="h-9 gap-1.5 px-2">
                <Link :href="adminCatalogIndex()">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    <span>Back to Catalog Products</span>
                </Link>
            </Button>
        </div>

        <AdminCatalogTabs active="products" />

        <form class="space-y-6" @submit.prevent="submitProduct">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {{
                            product ? 'Edit Product Details' : 'Create Product'
                        }}
                    </CardTitle>
                    <CardDescription>
                        Server-authoritative pricing, currency, SKU, and
                        inventory metadata.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="product-name">Product Name</Label>
                            <Input
                                id="product-name"
                                v-model="form.name"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-sku">Primary SKU</Label>
                            <Input
                                id="product-sku"
                                v-model="form.sku"
                                required
                                class="h-11 font-mono"
                            />
                            <InputError :message="form.errors.sku" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-slug"
                                >URL Slug (optional)</Label
                            >
                            <Input
                                id="product-slug"
                                v-model="form.slug"
                                placeholder="auto-generated-from-name"
                                class="h-11 font-mono"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-category">Category</Label>
                            <SelectField
                                id="product-category"
                                v-model="form.category_id"
                                :options="categoryOptions"
                                placeholder="Select category"
                                required
                            />
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-brand">Brand</Label>
                            <SelectField
                                id="product-brand"
                                v-model="form.brand_id"
                                :options="brandOptions"
                                empty-option-label="No Brand"
                            />
                            <InputError :message="form.errors.brand_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-status">Publish Status</Label>
                            <SelectField
                                id="product-status"
                                v-model="form.status"
                                :options="statuses"
                            />
                            <InputError :message="form.errors.status" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-seller"
                                >Seller Display Name</Label
                            >
                            <Input
                                id="product-seller"
                                v-model="form.seller_name"
                                class="h-11"
                            />
                            <InputError :message="form.errors.seller_name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-price">Base Price (PHP)</Label>
                            <Input
                                id="product-price"
                                v-model="form.base_price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.base_price" />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-compare"
                                >Compare-at Price (PHP)</Label
                            >
                            <Input
                                id="product-compare"
                                v-model="form.compare_at_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="h-11"
                            />
                            <InputError
                                :message="form.errors.compare_at_price"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="product-stock">Stock Quantity</Label>
                            <Input
                                id="product-stock"
                                v-model.number="form.stock_quantity"
                                type="number"
                                min="0"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.stock_quantity" />
                        </div>

                        <div class="flex items-center gap-3 pt-6">
                            <Checkbox
                                id="product-featured"
                                :model-value="form.is_featured"
                                @update:model-value="
                                    form.is_featured = $event === true
                                "
                            />
                            <Label
                                for="product-featured"
                                class="cursor-pointer font-medium"
                            >
                                Feature on Storefront Top Products
                            </Label>
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="product-short">Short Summary</Label>
                            <Input
                                id="product-short"
                                v-model="form.short_description"
                                class="h-11"
                            />
                            <InputError
                                :message="form.errors.short_description"
                            />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="product-desc">Full Description</Label>
                            <textarea
                                id="product-desc"
                                v-model="form.description"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <!-- Specifications -->
                    <div class="space-y-3 border-t border-border pt-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Specifications
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Key/value technical specifications shown on
                                    the product detail page.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1.5"
                                @click="addSpecRow"
                            >
                                <Plus class="size-3.5" aria-hidden="true" />
                                <span>Add Spec</span>
                            </Button>
                        </div>

                        <div
                            v-for="(row, idx) in specRows"
                            :key="idx"
                            class="grid grid-cols-[1fr_1fr_auto] gap-2"
                        >
                            <Input
                                v-model="row.key"
                                placeholder="Attribute (e.g. Weight)"
                                class="h-10"
                            />
                            <Input
                                v-model="row.value"
                                placeholder="Value (e.g. 265g)"
                                class="h-10"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-10 text-destructive"
                                aria-label="Remove specification"
                                @click="removeSpecRow(idx)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>

                    <!-- Product Variants -->
                    <div class="space-y-3 border-t border-border pt-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Product Variants (Color / Size SKUs)
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    When active variants are defined, default
                                    variant price &amp; sum of variant stock
                                    govern catalog totals automatically.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-9 gap-1.5"
                                @click="addVariantRow"
                            >
                                <Plus class="size-3.5" aria-hidden="true" />
                                <span>Add Variant</span>
                            </Button>
                        </div>

                        <div
                            v-for="(variant, idx) in form.variants"
                            :key="idx"
                            class="grid grid-cols-1 gap-3 rounded-xl border border-border bg-secondary/20 p-4 sm:grid-cols-6"
                        >
                            <div class="space-y-1 sm:col-span-2">
                                <Label
                                    :for="`var-title-${idx}`"
                                    class="text-xs"
                                >
                                    Variant Title
                                </Label>
                                <Input
                                    :id="`var-title-${idx}`"
                                    v-model="variant.title"
                                    required
                                    placeholder="Matte Charcoal / 8L"
                                    class="h-10"
                                />
                            </div>

                            <div class="space-y-1 sm:col-span-2">
                                <Label :for="`var-sku-${idx}`" class="text-xs">
                                    Variant SKU
                                </Label>
                                <Input
                                    :id="`var-sku-${idx}`"
                                    v-model="variant.sku"
                                    required
                                    placeholder="SKU-VAR-01"
                                    class="h-10 font-mono"
                                />
                            </div>

                            <div class="space-y-1">
                                <Label
                                    :for="`var-price-${idx}`"
                                    class="text-xs"
                                >
                                    Price (PHP)
                                </Label>
                                <Input
                                    :id="`var-price-${idx}`"
                                    v-model="variant.price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="h-10"
                                />
                            </div>

                            <div class="space-y-1">
                                <Label
                                    :for="`var-stock-${idx}`"
                                    class="text-xs"
                                >
                                    Stock
                                </Label>
                                <Input
                                    :id="`var-stock-${idx}`"
                                    v-model.number="variant.stock_quantity"
                                    type="number"
                                    min="0"
                                    required
                                    class="h-10"
                                />
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-between gap-3 sm:col-span-6"
                            >
                                <div class="flex items-center gap-4 text-xs">
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="variant.is_default"
                                            type="checkbox"
                                        />
                                        <span>Default option</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="variant.is_active"
                                            type="checkbox"
                                        />
                                        <span>Active</span>
                                    </label>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 text-xs text-destructive"
                                    @click="removeVariantRow(idx)"
                                >
                                    Remove Variant
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button
                            type="submit"
                            class="h-11 px-6 font-semibold"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : product
                                      ? 'Update Product'
                                      : 'Create Product'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>

        <!-- Product Media Gallery Manager (after initial save) -->
        <Card v-if="product">
            <CardHeader>
                <CardTitle>Product Media Gallery</CardTitle>
                <CardDescription>
                    Uploaded JPG, PNG, or WebP files are validated by content
                    signature, re-encoded, and stored privately.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <form
                    class="grid grid-cols-1 gap-4 rounded-xl border border-border bg-secondary/20 p-4 sm:grid-cols-2"
                    @submit.prevent="submitImageUpload"
                >
                    <div class="space-y-1.5">
                        <Label for="media-file"
                            >Image File (JPG/PNG/WebP)</Label
                        >
                        <Input
                            id="media-file"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            required
                            class="h-11 pt-2"
                            @change="handleFileSelection"
                        />
                        <InputError :message="imageForm.errors.image" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="media-alt"
                            >Accessible Alt Description</Label
                        >
                        <Input
                            id="media-alt"
                            v-model="imageForm.alt_text"
                            required
                            class="h-11"
                        />
                        <InputError :message="imageForm.errors.alt_text" />
                    </div>

                    <div v-if="product.variants.length > 0" class="space-y-1.5">
                        <Label for="media-variant"
                            >Associate Variant (Optional)</Label
                        >
                        <SelectField
                            id="media-variant"
                            v-model="imageForm.variant_id"
                            :options="variantOptions"
                            empty-option-label="All Variants / General"
                        />
                    </div>

                    <div class="flex items-end justify-between gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="imageForm.is_primary"
                                type="checkbox"
                            />
                            <span>Set as primary image</span>
                        </label>

                        <Button
                            type="submit"
                            class="h-11 gap-2"
                            :disabled="imageForm.processing || !imageForm.image"
                        >
                            <ImagePlus class="size-4" aria-hidden="true" />
                            <span>Upload Image</span>
                        </Button>
                    </div>
                </form>

                <div
                    v-if="product.images.length > 0"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                >
                    <div
                        v-for="img in product.images"
                        :key="img.id"
                        class="flex flex-col overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <div class="relative aspect-square bg-secondary/40">
                            <img
                                :src="img.url"
                                :alt="img.alt_text"
                                class="size-full object-cover"
                            />
                            <Badge
                                v-if="img.is_primary"
                                class="absolute top-2 left-2"
                            >
                                Primary
                            </Badge>
                        </div>
                        <div
                            class="flex items-center justify-between gap-1 p-2.5"
                        >
                            <Button
                                v-if="!img.is_primary"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 text-xs"
                                @click="makePrimary(img.id)"
                            >
                                <Star class="size-3.5" aria-hidden="true" />
                                <span>Make Primary</span>
                            </Button>
                            <span
                                v-else
                                class="px-2 text-xs font-medium text-muted-foreground"
                            >
                                Featured
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-destructive"
                                aria-label="Remove product image"
                                @click="removeImage(img.id)"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
