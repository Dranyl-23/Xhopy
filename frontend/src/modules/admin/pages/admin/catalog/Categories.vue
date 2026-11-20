<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { SelectField, type SelectOption } from '@shared/components/ui/select';
import {
    destroy as destroyCategory,
    store as storeCategory,
    update as updateCategory,
} from '@/routes/admin/catalog/categories';
import type { AdminCategoryRow } from '@modules/admin/types';

const props = defineProps<{
    categories: AdminCategoryRow[];
}>();

const page = usePage();
const editingId = ref<number | null>(null);
const parentCategoryOptions = computed<SelectOption<number>[]>(() =>
    props.categories.map((cat) => ({
        value: cat.id,
        label: cat.name,
        disabled: cat.id === editingId.value,
    })),
);
const categoryError = computed(
    () =>
        (page.props.errors as Record<string, string | undefined> | undefined)
            ?.category,
);

const form = useForm({
    parent_id: '' as number | '',
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

function startEdit(cat: AdminCategoryRow): void {
    editingId.value = cat.id;
    form.parent_id = cat.parent_id ?? '';
    form.name = cat.name;
    form.slug = cat.slug;
    form.description = cat.description;
    form.sort_order = cat.sort_order;
    form.is_active = cat.is_active;
}

function resetForm(): void {
    editingId.value = null;
    form.reset();
    form.is_active = true;
}

function submitCategory(): void {
    const payload = form.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : Number(data.parent_id),
    }));

    if (editingId.value !== null) {
        payload.patch(updateCategory.url(editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        payload.post(storeCategory.url(), {
            onSuccess: () => resetForm(),
        });
    }
}

function deleteCategory(cat: AdminCategoryRow): void {
    if (!window.confirm(`Delete category "${cat.name}"?`)) {
        return;
    }

    router.delete(destroyCategory.url(cat.id));
}
</script>

<template>
    <Head title="Categories — Admin Catalog" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Catalog Categories
            </h1>
            <p class="text-sm text-muted-foreground">
                Organize departments and parent/child navigation groups.
            </p>
        </div>

        <AdminCatalogTabs active="categories" />

        <InputError :message="categoryError" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <Card class="lg:col-span-5">
                <CardHeader>
                    <CardTitle>{{
                        editingId ? 'Edit Category' : 'Add Category'
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCategory">
                        <div class="space-y-1.5">
                            <Label for="cat-name">Category Name</Label>
                            <Input
                                id="cat-name"
                                v-model="form.name"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="cat-slug">Slug (optional)</Label>
                            <Input
                                id="cat-slug"
                                v-model="form.slug"
                                placeholder="auto-from-name"
                                class="h-11 font-mono"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="cat-parent">Parent Category</Label>
                            <SelectField
                                id="cat-parent"
                                v-model="form.parent_id"
                                :options="parentCategoryOptions"
                                empty-option-label="Top-Level Department"
                            />
                            <InputError :message="form.errors.parent_id" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="cat-sort">Sort Order</Label>
                                <Input
                                    id="cat-sort"
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="h-11"
                                />
                            </div>
                            <label class="flex items-center gap-2 pt-6 text-sm">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                />
                                <span>Active</span>
                            </label>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="cat-desc">Description</Label>
                            <Input
                                id="cat-desc"
                                v-model="form.description"
                                class="h-11"
                            />
                        </div>

                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                class="h-11 flex-1"
                                :disabled="form.processing"
                            >
                                {{ editingId ? 'Update' : 'Create Category' }}
                            </Button>
                            <Button
                                v-if="editingId"
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
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Products</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="cat in categories" :key="cat.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ cat.name }}</p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ cat.slug }}
                                    <span v-if="cat.parent_name">
                                        &middot; under {{ cat.parent_name }}
                                    </span>
                                </p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ cat.products_count }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        cat.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ cat.is_active ? 'Active' : 'Hidden' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    aria-label="Edit category"
                                    @click="startEdit(cat)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive"
                                    aria-label="Delete category"
                                    @click="deleteCategory(cat)"
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
