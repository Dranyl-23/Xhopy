<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Building2,
    ClipboardList,
    LayoutGrid,
    MapPin,
    PackageCheck,
    ReceiptText,
    ShieldCheck,
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
import { index as addressesIndex } from '@/routes/addresses';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as catalogIndex } from '@/routes/catalog';
import { index as ordersIndex } from '@/routes/orders';
import {
    dashboard as sellerDashboard,
    onboarding as sellerOnboarding,
} from '@/routes/seller';
import type { NavItem } from '@shared/types';

const page = usePage();

const sidebarHomeHref = computed(() => {
    const auth = page.props.auth;
    if (auth?.canManageOperations) {
        return adminOperationsIndex();
    }
    if (auth?.canReviewSellers) {
        return adminSellersIndex();
    }
    if (auth?.canManageCatalog) {
        return adminCatalogIndex();
    }
    if (auth?.isApprovedSeller) {
        return sellerDashboard();
    }

    return dashboard();
});

const customerNavItems = computed<NavItem[]>(() => [
    {
        title: 'My Account',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'My Orders',
        href: ordersIndex(),
        icon: ReceiptText,
    },
    {
        title: 'Saved Addresses',
        href: addressesIndex(),
        icon: MapPin,
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
]);

const sellerNavItems = computed<NavItem[]>(() => {
    const auth = page.props.auth;
    if (!auth || auth.canAccessAdmin) {
        return [];
    }

    if (auth.isApprovedSeller) {
        return [
            {
                title: 'Seller Dashboard',
                href: sellerDashboard(),
                icon: Building2,
            },
            {
                title: 'Store Verification',
                href: sellerOnboarding(),
                icon: ShieldCheck,
            },
        ];
    }

    return [
        {
            title: auth.isSeller ? 'Seller Onboarding' : 'Become a Seller',
            href: sellerOnboarding(),
            icon: Building2,
        },
    ];
});

const adminNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    const auth = page.props.auth;

    if (auth?.canManageOperations) {
        items.push({
            title: 'Operations & Analytics',
            href: adminOperationsIndex(),
            icon: BarChart3,
        });
    }

    if (auth?.canManageOrders) {
        items.push({
            title: 'Admin Orders',
            href: adminOrdersIndex(),
            icon: ClipboardList,
        });
    }

    if (auth?.canManageInventory) {
        items.push({
            title: 'Inventory',
            href: adminInventoryIndex(),
            icon: Boxes,
        });
    }

    if (auth?.canManageCatalog) {
        items.push({
            title: 'Catalog Admin',
            href: adminCatalogIndex(),
            icon: PackageCheck,
        });
    }

    if (auth?.canReviewSellers) {
        items.push({
            title: 'Seller Applications',
            href: adminSellersIndex(),
            icon: Building2,
        });
    }

    if (auth?.canManageUsers) {
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
                        <Link :href="sidebarHomeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain
                v-if="adminNavItems.length > 0"
                label="Administration"
                :items="adminNavItems"
            />
            <NavMain
                v-if="sellerNavItems.length > 0"
                label="Seller Portal"
                :items="sellerNavItems"
            />
            <NavMain label="Customer Account" :items="customerNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
