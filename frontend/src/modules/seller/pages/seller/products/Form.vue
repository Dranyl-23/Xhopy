<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock3,
    ImagePlus,
    Plus,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { Checkbox } from '@shared/components/ui/checkbox';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField, type SelectOption } from '@shared/components/ui/select';
import {
    index as sellerProductsIndex,
    store as storeSellerProduct,
    update as updateSellerProduct,
} from '@/routes/seller/products';
import {
    destroy as destroySellerImage,
    primary as setSellerPrimaryImage,
    store as uploadSellerImage,
} from '@/routes/seller/products/images';
import type { SellerProductFormProps } from '@modules/seller/types';

const props = defineProps<SellerProductFormProps>();

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

const selectableStatusValues = computed(() =>
    props.statuses.map((item) => item.value),
);

const initialStatus = computed(() => {
    const current = props.product?.status;
    if (current && selectableStatusValues.value.includes(current)) {
        return current;
    }

    return props.product ? 'pending_review' : 'draft';
});

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
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    sku: props.product?.sku ?? '',
    short_description: props.product?.short_description ?? '',
    description: props.product?.description ?? '',
    status: initialStatus.value,
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

watch(
    () => props.product?.name,
    (nextName) => {
        if (nextName && imageForm.alt_text.trim() === '') {
            imageForm.alt_text = nextName;
        }
    },
    { immediate: true },
);

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
        form.patch(updateSellerProduct.url(props.product.id), {
            preserveScroll: true,
        });
    } else {
        form.post(storeSellerProduct.url());
    }
}

const mediaFileInput = ref<HTMLInputElement | null>(null);
const maxUploadBytes = computed(
    () => props.maxImageUploadBytes ?? 2 * 1024 * 1024,
);
const maxUploadMbLabel = computed(() =>
    (maxUploadBytes.value / (1024 * 1024)).toFixed(1).replace(/\.0$/, ''),
);

function handleFileSelection(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;
    imageForm.clearErrors('image');

    if (selected && selected.size > maxUploadBytes.value) {
        imageForm.image = null;
        imageForm.setError(
            'image',
            `Selected file (${(selected.size / (1024 * 1024)).toFixed(2)} MB) exceeds the current ${maxUploadMbLabel.value} MB upload limit.`,
        );
        return;
    }

    imageForm.image = selected;
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
        .post(uploadSellerImage.url(props.product.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                imageForm.reset('image', 'is_primary');
                if (mediaFileInput.value) {
                    mediaFileInput.value.value = '';
                }
            },
        });
}

function makePrimary(imageId: number): void {
    if (!props.product) {
        return;
    }

    router.patch(
        setSellerPrimaryImage.url({
            product: props.product.id,
            image: imageId,
        }),
        {},
        { preserveScroll: true },
    );
}

function removeImage(imageId: number): void {
    if (!props.product) {
        return;
    }

    router.delete(
        destroySellerImage.url({ product: props.product.id, image: imageId }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head
        :title="
            product
                ? `Edit ${product.name} — ${store.name}`
                : `New Product — ${store.name}`
        "
    />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <Button as-child variant="ghost" size="sm" class="h-9 gap-1.5 px-2">
                <Link :href="sellerProductsIndex()">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    <span>Back to Store Products</span>
                </Link>
            </Button>

            <Badge v-if="product" variant="secondary">
                Current status: {{ product.status_label }}
            </Badge>
        </div>

        <Alert
            v-if="product?.status === 'rejected'"
            variant="destructive"
            class="rounded-xl"
        >
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>Admin Moderation Requested Changes</AlertTitle>
            <AlertDescription>
                {{
                    product.moderation_notes ??
                    'Update the listing details and submit again for review.'
                }}
            </AlertDescription>
        </Alert>

        <Alert v-else-if="product?.status === 'pending_review'">
            <Clock3 class="size-4" aria-hidden="true" />
            <AlertTitle>Pending Moderation Review</AlertTitle>
            <AlertDescription>
                Submitted
                {{ product.submitted_for_review_at ?? 'for review' }}. This
                product goes live automatically once approved by XHopy Admin.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="product?.status === 'active'">
            <CheckCircle2 class="size-4" aria-hidden="true" />
            <AlertTitle>Approved &amp; Live on Storefront</AlertTitle>
            <AlertDescription>
                Saving catalog content or pricing changes will resubmit this
                product for Admin moderation review before returning live. To
                change stock counts without re-review, use Inventory &amp; CSV.
            </AlertDescription>
        </Alert>

        <form class="space-y-6" @submit.prevent="submitProduct">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {{
                            product
                                ? 'Edit Store Product'
                                : 'Create Store Product'
                        }}
                    </CardTitle>
                    <CardDescription>
                        Store owner:
                        <span class="font-medium text-foreground">{{
                            store.name
                        }}</span>
                        · Listings must be approved by XHopy Admin before going
                        live.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="seller-product-name"
                                >Product Name</Label
                            >
                            <Input
                                id="seller-product-name"
                                v-model="form.name"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="seller-product-sku">Primary SKU</Label>
                            <Input
                                id="seller-product-sku"
                                v-model="form.sku"
                                required
                                class="h-11 font-mono"
                            />
                            <InputError :message="form.errors.sku" />
                        </div>

                        <div class="space-y-2">
                            <Label for="seller-product-slug"
                                >URL Slug (optional)</Label
                            >
                            <Input
                                id="seller-product-slug"
                                v-model="form.slug"
                                placeholder="auto-generated-from-name"
                                class="h-11 font-mono"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>

                        <div class="space-y-2">
                            <Label for="seller-product-category"
                                >Category</Label
                            >
                            <SelectField
                                id="seller-product-category"
                                v-model="form.category_id"
                                :options="categoryOptions"
                                placeholder="Select category"
                                required
                            />
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="space-y-2">
                            <Label for="seller-product-brand">Brand</Label>
                            <SelectField
                                id="seller-product-brand"
                                v-model="form.brand_id"
                                :options="brandOptions"
                                empty-option-label="No Brand"
                            />
                            <InputError :message="form.errors.brand_id" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="seller-product-status"
                                >Submission Action</Label
                            >
                            <SelectField
                                id="seller-product-status"
                                v-model="form.status"
                                :options="statuses"
                            />
                            <InputError :message="form.errors.status" />
                            <p class="text-xs text-muted-foreground">
                                Choose <strong>Pending Review</strong> to submit
                                to XHopy moderators, or <strong>Draft</strong>
                                to keep editing privately.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="seller-product-price"
                                >Base Price (PHP)</Label
                            >
                            <Input
                                id="seller-product-price"
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
                            <Label for="seller-product-compare"
                                >Compare-at Price (PHP)</Label
                            >
                            <Input
                                id="seller-product-compare"
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
                            <Label for="seller-product-stock"
                                >Stock Quantity</Label
                            >
                            <Input
                                id="seller-product-stock"
                                v-model.number="form.stock_quantity"
                                type="number"
                                min="0"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.stock_quantity" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="seller-product-short"
                                >Short Summary</Label
                            >
                            <Input
                                id="seller-product-short"
                                v-model="form.short_description"
                                class="h-11"
                            />
                            <InputError
                                :message="form.errors.short_description"
                            />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="seller-product-desc"
                                >Full Description</Label
                            >
                            <textarea
                                id="seller-product-desc"
                                v-model="form.description"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <!-- Specifications -->
                    <div class="space-y-3 border-t border-border pt-5">
                        <div class="flex items-center justify-between gap-3">
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
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Product Variants (Color / Size SKUs)
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    When active variants are defined, default
                                    variant price and sum of variant stock
                                    govern totals automatically.
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
                            class="grid grid-cols-1 gap-3 rounded-lg border border-border bg-secondary/20 p-3 sm:grid-cols-6"
                        >
                            <div class="space-y-1 sm:col-span-2">
                                <Label>Variant Title</Label>
                                <Input
                                    v-model="variant.title"
                                    placeholder="Matte Black · Large"
                                    required
                                    class="h-10"
                                />
                            </div>
                            <div class="space-y-1 sm:col-span-2">
                                <Label>Variant SKU</Label>
                                <Input
                                    v-model="variant.sku"
                                    placeholder="STORE-SKU-BLK-L"
                                    required
                                    class="h-10 font-mono"
                                />
                                <InputError
                                    :message="
                                        (form.errors as Record<string, string>)[
                                            `variants.${idx}.sku`
                                        ]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Price (PHP)</Label>
                                <Input
                                    v-model="variant.price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="h-10"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label>Stock</Label>
                                <Input
                                    v-model.number="variant.stock_quantity"
                                    type="number"
                                    min="0"
                                    required
                                    class="h-10"
                                />
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 sm:col-span-6"
                            >
                                <div class="flex items-center gap-5 text-xs">
                                    <label
                                        class="flex cursor-pointer items-center gap-2"
                                    >
                                        <Checkbox
                                            :model-value="variant.is_default"
                                            @update:model-value="
                                                variant.is_default =
                                                    $event === true
                                            "
                                        />
                                        <span>Default option</span>
                                    </label>
                                    <label
                                        class="flex cursor-pointer items-center gap-2"
                                    >
                                        <Checkbox
                                            :model-value="variant.is_active"
                                            @update:model-value="
                                                variant.is_active =
                                                    $event === true
                                            "
                                        />
                                        <span>Variant active</span>
                                    </label>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 gap-1 text-destructive"
                                    @click="removeVariantRow(idx)"
                                >
                                    <Trash2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>Remove</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button
                            type="submit"
                            class="h-11 min-w-[180px] font-semibold"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : product
                                      ? 'Save Product Changes'
                                      : 'Create Store Product'
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
                        <Label for="seller-media-file">
                            Image File (JPG/PNG/WebP · max
                            {{ maxUploadMbLabel }} MB)
                        </Label>
                        <input
                            id="seller-media-file"
                            ref="mediaFileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            required
                            class="block h-11 w-full cursor-pointer rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-xs outline-none file:mr-3 file:rounded file:border-0 file:bg-secondary file:px-2.5 file:py-1 file:text-xs file:font-medium file:text-secondary-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            @change="handleFileSelection"
                        />
                        <InputError :message="imageForm.errors.image" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="seller-media-alt"
                            >Accessible Alt Description</Label
                        >
                        <Input
                            id="seller-media-alt"
                            v-model="imageForm.alt_text"
                            required
                            class="h-11"
                        />
                        <InputError :message="imageForm.errors.alt_text" />
                    </div>

                    <div v-if="product.variants.length > 0" class="space-y-1.5">
                        <Label for="seller-media-variant"
                            >Associate Variant (Optional)</Label
                        >
                        <SelectField
                            id="seller-media-variant"
                            v-model="imageForm.variant_id"
                            :options="variantOptions"
                            empty-option-label="All Variants / General"
                        />
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-4 border-t border-border/60 pt-3 sm:col-span-2"
                    >
                        <label
                            class="flex min-h-11 cursor-pointer items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :model-value="imageForm.is_primary"
                                @update:model-value="
                                    imageForm.is_primary = $event === true
                                "
                            />
                            <span>Set as primary image</span>
                        </label>

                        <Button
                            type="submit"
                            class="h-11 min-w-[160px] justify-center gap-2"
                            :disabled="imageForm.processing || !imageForm.image"
                        >
                            <ImagePlus class="size-4" aria-hidden="true" />
                            <span>{{
                                imageForm.processing
                                    ? 'Uploading...'
                                    : 'Upload Image'
                            }}</span>
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
                                class="px-2 text-xs text-muted-foreground"
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
                                <Trash2 class="size-3.5" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
