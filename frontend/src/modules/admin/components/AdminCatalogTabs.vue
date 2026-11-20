<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink, Layers, Megaphone, Package, Tag } from '@lucide/vue';
import { Button } from '@shared/components/ui/button';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as brandsIndex } from '@/routes/admin/catalog/brands';
import { index as campaignsIndex } from '@/routes/admin/catalog/campaigns';
import { index as categoriesIndex } from '@/routes/admin/catalog/categories';
import { index as catalogIndex } from '@/routes/catalog';

defineProps<{
    active: 'products' | 'categories' | 'brands' | 'campaigns';
}>();

const tabs = [
    {
        id: 'products' as const,
        label: 'Products',
        href: adminCatalogIndex(),
        icon: Package,
    },
    {
        id: 'categories' as const,
        label: 'Categories',
        href: categoriesIndex(),
        icon: Layers,
    },
    {
        id: 'brands' as const,
        label: 'Brands',
        href: brandsIndex(),
        icon: Tag,
    },
    {
        id: 'campaigns' as const,
        label: 'Daily Interest & Banners',
        href: campaignsIndex(),
        icon: Megaphone,
    },
];
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4"
    >
        <nav aria-label="Catalog administration" class="flex flex-wrap gap-2">
            <Button
                v-for="tab in tabs"
                :key="tab.id"
                as-child
                :variant="active === tab.id ? 'default' : 'outline'"
                size="sm"
                class="h-10 gap-2"
            >
                <Link :href="tab.href">
                    <component
                        :is="tab.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <span>{{ tab.label }}</span>
                </Link>
            </Button>
        </nav>

        <Button as-child variant="ghost" size="sm" class="h-10 gap-1.5 text-xs">
            <Link :href="catalogIndex()">
                <span>View Public Catalog</span>
                <ExternalLink class="size-3.5" aria-hidden="true" />
            </Link>
        </Button>
    </div>
</template>
