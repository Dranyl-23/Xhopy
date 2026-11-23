<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Building2,
    ClipboardList,
    Package,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@shared/components/ui/button';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { index as adminUsersIndex } from '@/routes/admin/users';

defineProps<{
    active: 'operations' | 'orders' | 'inventory' | 'sellers';
}>();

const page = usePage();

const tabs = computed(() => {
    const auth = page.props.auth;
    const list: Array<{
        id: 'operations' | 'orders' | 'inventory' | 'sellers';
        label: string;
        href: ReturnType<typeof adminOperationsIndex>;
        icon: typeof BarChart3;
    }> = [];

    if (auth?.canManageOperations) {
        list.push({
            id: 'operations',
            label: 'Analytics & Audit',
            href: adminOperationsIndex(),
            icon: BarChart3,
        });
    }

    if (auth?.canManageOrders) {
        list.push({
            id: 'orders',
            label: 'Orders & Fulfillment',
            href: adminOrdersIndex(),
            icon: ClipboardList,
        });
    }

    if (auth?.canManageInventory) {
        list.push({
            id: 'inventory',
            label: 'Inventory & CSV',
            href: adminInventoryIndex(),
            icon: Boxes,
        });
    }

    if (auth?.canReviewSellers) {
        list.push({
            id: 'sellers',
            label: 'Seller Applications',
            href: adminSellersIndex(),
            icon: Building2,
        });
    }

    return list;
});
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
                v-if="page.props.auth?.canManageCatalog"
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
