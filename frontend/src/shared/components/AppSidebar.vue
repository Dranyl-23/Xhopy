<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    ClipboardList,
    LayoutGrid,
    PackageCheck,
    ReceiptText,
    ShoppingBag,
    Store,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@shared/components/AppLogo.vue';
import NavFooter from '@shared/components/NavFooter.vue';
import NavMain from '@shared/components/NavMain.vue';
import NavUser from '@shared/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@shared/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as catalogIndex } from '@/routes/catalog';
import { index as ordersIndex } from '@/routes/orders';
import type { NavItem } from '@shared/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Storefront',
            href: home(),
            icon: Store,
        },
        {
            title: 'Catalog',
            href: catalogIndex(),
            icon: ShoppingBag,
        },
        {
            title: 'My Orders',
            href: ordersIndex(),
            icon: ReceiptText,
        },
    ];

    if (
        page.props.auth?.canManageOperations ||
        page.props.auth?.canManageCatalog
    ) {
        items.push(
            {
                title: 'Operations & Analytics',
                href: adminOperationsIndex(),
                icon: BarChart3,
            },
            {
                title: 'Admin Orders',
                href: adminOrdersIndex(),
                icon: ClipboardList,
            },
            {
                title: 'Inventory',
                href: adminInventoryIndex(),
                icon: Boxes,
            },
        );
    }

    if (page.props.auth?.canManageCatalog) {
        items.push({
            title: 'Catalog Admin',
            href: adminCatalogIndex(),
            icon: PackageCheck,
        });
    }

    if (page.props.auth?.canManageUsers) {
        items.push({
            title: 'Users & Roles',
            href: adminUsersIndex(),
            icon: Users,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
