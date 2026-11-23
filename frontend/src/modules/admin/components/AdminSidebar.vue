<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    Building2,
    ClipboardList,
    PackageCheck,
    ShieldCheck,
    Store,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import BrandLogo from '@shared/components/BrandLogo.vue';
import NavMain from '@shared/components/NavMain.vue';
import NavUser from '@shared/components/NavUser.vue';
import { Badge } from '@shared/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@shared/components/ui/sidebar';
import { home } from '@/routes';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { edit as profileEdit } from '@/routes/profile';
import type { NavItem } from '@shared/types';

const page = usePage();

const adminHomeHref = computed(() => {
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

    return profileEdit();
});

const roleBadge = computed(() =>
    page.props.auth?.canManageUsers ? 'Super Admin' : 'Admin Staff',
);

const operationsNavItems = computed<NavItem[]>(() => {
    const auth = page.props.auth;
    const items: NavItem[] = [];

    if (auth?.canManageOperations) {
        items.push({
            title: 'Operations & Analytics',
            href: adminOperationsIndex(),
            icon: BarChart3,
        });
    }

    if (auth?.canReviewSellers) {
        items.push({
            title: 'Seller Applications',
            href: adminSellersIndex(),
            icon: Building2,
        });
    }

    if (auth?.canManageOrders) {
        items.push({
            title: 'Orders & Fulfillment',
            href: adminOrdersIndex(),
            icon: ClipboardList,
        });
    }

    if (auth?.canManageInventory) {
        items.push({
            title: 'Inventory & CSV',
            href: adminInventoryIndex(),
            icon: Boxes,
        });
    }

    return items;
});

const governanceNavItems = computed<NavItem[]>(() => {
    const auth = page.props.auth;
    const items: NavItem[] = [];

    if (auth?.canManageCatalog) {
        items.push({
            title: 'Catalog Admin',
            href: adminCatalogIndex(),
            icon: PackageCheck,
        });
    }

    if (auth?.canManageUsers) {
        items.push({
            title: 'Users & Roles',
            href: adminUsersIndex(),
            icon: Users,
        });
    }

    items.push({
        title: 'Profile & Security',
        href: profileEdit(),
        icon: ShieldCheck,
    });

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="gap-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="adminHomeHref">
                            <BrandLogo
                                name="XHopy"
                                subtitle="Admin Portal"
                                size="sm"
                                class="truncate"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div class="px-2 group-data-[collapsible=icon]:hidden">
                <Badge
                    variant="default"
                    class="w-full justify-center py-0.5 text-[11px] font-semibold"
                >
                    {{ roleBadge }}
                </Badge>
            </div>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain
                v-if="operationsNavItems.length > 0"
                label="Marketplace Operations"
                :items="operationsNavItems"
            />
            <NavMain
                v-if="governanceNavItems.length > 0"
                label="Governance &amp; Security"
                :items="governanceNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        tooltip="Public Storefront"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <Link :href="home()">
                            <Store />
                            <span>Public Storefront</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
