<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
    destroy as destroyCampaign,
    store as storeCampaign,
    update as updateCampaign,
} from '@/routes/admin/catalog/campaigns';
import type {
    AdminCampaignRow,
    AdminSelectOption,
    AdminStatusOption,
} from '@modules/admin/types';

const props = defineProps<{
    campaigns: AdminCampaignRow[];
    categories: AdminSelectOption[];
    placements: AdminStatusOption[];
}>();

const editingId = ref<number | null>(null);
const categoryOptions = computed<SelectOption<number>[]>(() =>
    props.categories.map((cat) => ({
        value: cat.id,
        label: cat.name,
    })),
);

const form = useForm({
    placement: props.placements[1]?.value ?? 'daily_interest_featured',
    tag: '',
    title: '',
    summary: '',
    cta_label: 'Browse Collection',
    cta_href: '/catalog',
    category_id: '' as number | '',
    sort_order: 0,
    is_active: true,
});

function startEdit(item: AdminCampaignRow): void {
    editingId.value = item.id;
    form.placement = item.placement;
    form.tag = item.tag;
    form.title = item.title;
    form.summary = item.summary;
    form.cta_label = item.cta_label;
    form.cta_href = item.cta_href;
    form.category_id = item.category_id ?? '';
    form.sort_order = item.sort_order;
    form.is_active = item.is_active;
}

function resetForm(): void {
    editingId.value = null;
    form.reset();
    form.cta_label = 'Browse Collection';
    form.cta_href = '/catalog';
    form.is_active = true;
}

function submitCampaign(): void {
    const payload = form.transform((data) => ({
        ...data,
        category_id: data.category_id === '' ? null : Number(data.category_id),
    }));

    if (editingId.value !== null) {
        payload.patch(updateCampaign.url(editingId.value), {
            onSuccess: () => resetForm(),
        });
    } else {
        payload.post(storeCampaign.url(), {
            onSuccess: () => resetForm(),
        });
    }
}

function deleteCampaign(item: AdminCampaignRow): void {
    if (!window.confirm(`Delete campaign "${item.title}"?`)) {
        return;
    }

    router.delete(destroyCampaign.url(item.id));
}
</script>

<template>
    <Head title="Daily Interest & Banners — Admin Catalog" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                Daily Interest &amp; Storefront Campaigns
            </h1>
            <p class="text-sm text-muted-foreground">
                Control active storefront announcements and Daily Interest
                editorial stories driven by real database records.
            </p>
        </div>

        <AdminCatalogTabs active="campaigns" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <Card class="lg:col-span-5">
                <CardHeader>
                    <CardTitle>{{
                        editingId ? 'Edit Campaign' : 'Add Campaign'
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitCampaign">
                        <div class="space-y-1.5">
                            <Label for="camp-placement">Placement</Label>
                            <SelectField
                                id="camp-placement"
                                v-model="form.placement"
                                :options="placements"
                            />
                            <InputError :message="form.errors.placement" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="camp-tag">Badge / Eyebrow Tag</Label>
                            <Input
                                id="camp-tag"
                                v-model="form.tag"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.tag" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="camp-title">Title / Headline</Label>
                            <Input
                                id="camp-title"
                                v-model="form.title"
                                required
                                class="h-11"
                            />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="camp-summary">Summary</Label>
                            <Input
                                id="camp-summary"
                                v-model="form.summary"
                                class="h-11"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="camp-cta">CTA Label</Label>
                                <Input
                                    id="camp-cta"
                                    v-model="form.cta_label"
                                    class="h-11"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="camp-href">Target Path</Label>
                                <Input
                                    id="camp-href"
                                    v-model="form.cta_href"
                                    placeholder="/catalog"
                                    class="h-11 font-mono"
                                />
                                <InputError :message="form.errors.cta_href" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label for="camp-category"
                                    >Linked Category</Label
                                >
                                <SelectField
                                    id="camp-category"
                                    v-model="form.category_id"
                                    :options="categoryOptions"
                                    empty-option-label="None"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="camp-sort">Sort Order</Label>
                                <Input
                                    id="camp-sort"
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="h-11"
                                />
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" />
                            <span>Active on storefront</span>
                        </label>

                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                class="h-11 flex-1"
                                :disabled="form.processing"
                            >
                                {{
                                    editingId
                                        ? 'Update Campaign'
                                        : 'Create Campaign'
                                }}
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
                            <th class="px-4 py-3">Campaign</th>
                            <th class="px-4 py-3">Placement</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="camp in campaigns" :key="camp.id">
                            <td class="px-4 py-3">
                                <p class="text-xs font-semibold text-primary">
                                    {{ camp.tag }}
                                </p>
                                <p class="font-semibold">{{ camp.title }}</p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ camp.cta_href }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ camp.placementLabel }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        camp.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ camp.is_active ? 'Active' : 'Hidden' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    aria-label="Edit campaign"
                                    @click="startEdit(camp)"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive"
                                    aria-label="Delete campaign"
                                    @click="deleteCampaign(camp)"
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
