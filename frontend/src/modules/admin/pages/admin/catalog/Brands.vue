<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminCatalogTabs from '@modules/admin/components/AdminCatalogTabs.vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import {
    destroy as destroyBrand,
    store as storeBrand,
    update as updateBrand,
} from '@/routes/admin/catalog/brands';
import type { AdminBrandRow } from '@modules/admin/types';

defineProps<{
    brands: AdminBrandRow[];
}>();

const editingBrandId = ref<number | null>(null);
const websitePlaceholder = 'https://' + 'example.com';

const brandForm = useForm({
    name: '',
    slug: '',
    description: '',
    website_url: '',
    is_active: true,
});

function startEdit(brand: AdminBrandRow): void {
    editingBrandId.value = brand.id;
    brandForm.name = brand.name;
    brandForm.slug = brand.slug;
    brandForm.description = brand.description;
    brandForm.website_url = brand.website_url;
    brandForm.is_active = brand.is_active;
}

function resetForm(): void {
    editingBrandId.value = null;
    brandForm.reset();
    brandForm.is_active = true;
}

function submitBrand(): void {
    if (editingBrandId.value !== null) {
        brandForm.patch(updateBrand.url(editingBrandId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        brandForm.post(storeBrand.url(), {
            onSuccess: () => resetForm(),
        });
    }
}

function deleteBrand(brand: AdminBrandRow): void {
    if (!window.confirm(`Delete brand "${brand.name}"?`)) {
        return;
    }

    router.delete(destroyBrand.url(brand.id));
}
</script>

<template>
    <Head title="Brands — Admin Catalog" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Catalog Brands</h1>
            <p class="text-sm text-muted-foreground">
                Manage official product brands shown in catalog filters and
                product specifications.
            </p>
        </div>

        <AdminCatalogTabs active="brands" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <Card class="lg:col-span-5">
                <CardHeader>
                    <CardTitle>{{
                        editingBrandId ? 'Edit Brand' : 'Add Brand'
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitBrand">
                        <div class="space-y-1.5">
                            <Label for="brand-name">Brand Name</Label>
                            <Input
                                id="brand-name"
                                v-model="brandForm.name"
                                required
                                class="h-11"
                            />
                            <InputError :message="brandForm.errors.name" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="brand-slug">Slug (optional)</Label>
                            <Input
                                id="brand-slug"
                                v-model="brandForm.slug"
                                placeholder="auto-from-name"
                                class="h-11 font-mono"
                            />
                            <InputError :message="brandForm.errors.slug" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="brand-web">
                                Website URL (optional)
                            </Label>
                            <Input
                                id="brand-web"
                                v-model="brandForm.website_url"
                                type="url"
                                :placeholder="websitePlaceholder"
                                class="h-11"
                            />
                            <InputError
                                :message="brandForm.errors.website_url"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="brand-desc">Description</Label>
                            <Input
                                id="brand-desc"
                                v-model="brandForm.description"
                                class="h-11"
                            />
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="brandForm.is_active"
                                type="checkbox"
                            />
                            <span>Active</span>
                        </label>

                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                class="h-11 flex-1"
                                :disabled="brandForm.processing"
                            >
                                {{
                                    editingBrandId
                                        ? 'Update Brand'
                                        : 'Create Brand'
                                }}
                            </Button>
                            <Button
                                v-if="editingBrandId"
                                type="button"
                                variant="outline"
                                class="h-11"
                                @click="resetForm"
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div
                class="overflow-x-auto rounded-xl border border-border bg-card lg:col-span-7"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Brand</th>
                            <th class="px-4 py-3">Products</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="brand in brands" :key="brand.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ brand.name }}</p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ brand.slug }}
                                </p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ brand.products_count }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        brand.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ brand.is_active ? 'Active' : 'Hidden' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    aria-label="Edit brand"
                                    @click="startEdit(brand)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive"
                                    aria-label="Delete brand"
                                    @click="deleteBrand(brand)"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
