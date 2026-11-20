<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Boxes, ClipboardList, Package, Users } from '@lucide/vue';
import { Button } from '@shared/components/ui/button';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminUsersIndex } from '@/routes/admin/users';

defineProps<{
    active: 'operations' | 'orders' | 'inventory';
}>();

const page = usePage();

const tabs = [
    {
        id: 'operations' as const,
        label: 'Analytics & Audit',
        href: adminOperationsIndex(),
        icon: BarChart3,
    },
    {
        id: 'orders' as const,
        label: 'Orders & Fulfillment',
        href: adminOrdersIndex(),
        icon: ClipboardList,
    },
    {
        id: 'inventory' as const,
        label: 'Inventory & CSV',
        href: adminInventoryIndex(),
        icon: Boxes,
    },
];
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4"
    >
        <nav aria-label="Operations navigation" class="flex flex-wrap gap-2">
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

        <div class="flex flex-wrap items-center gap-2">
            <Button
                as-child
                variant="ghost"
                size="sm"
                class="h-10 gap-1.5 text-xs"
            >
                <Link :href="adminCatalogIndex()">
                    <Package class="size-3.5" aria-hidden="true" />
                    <span>Catalog Admin</span>
                </Link>
            </Button>

            <Button
                v-if="page.props.auth?.canManageUsers"
                as-child
                variant="ghost"
                size="sm"
                class="h-10 gap-1.5 text-xs"
            >
                <Link :href="adminUsersIndex()">
                    <Users class="size-3.5" aria-hidden="true" />
                    <span>Users &amp; Customers</span>
                </Link>
            </Button>
        </div>
    </div>
</template>
