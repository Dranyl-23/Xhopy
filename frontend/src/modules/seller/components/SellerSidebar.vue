<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    Building2,
    ClipboardList,
    LayoutGrid,
    Package,
    Settings,
    ShieldCheck,
    Store,
    Wallet,
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
import { dashboard, home } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import {
    dashboard as sellerDashboard,
    onboarding as sellerOnboarding,
} from '@/routes/seller';
import { index as sellerFinanceIndex } from '@/routes/seller/finance';
import { index as sellerInventoryIndex } from '@/routes/seller/inventory';
import { index as sellerOrdersIndex } from '@/routes/seller/orders';
import { index as sellerProductsIndex } from '@/routes/seller/products';
import { edit as sellerStoreEdit } from '@/routes/seller/store';
import type { NavItem } from '@shared/types';

const page = usePage();

const isApproved = computed(() => Boolean(page.props.auth?.isApprovedSeller));

const sellerHomeHref = computed(() =>
    isApproved.value ? sellerDashboard() : sellerOnboarding(),
);

const storeNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (isApproved.value) {
        items.push(
            {
                title: 'Seller Dashboard',
                href: sellerDashboard(),
                icon: Building2,
            },
            {
                title: 'Store Profile',
                href: sellerStoreEdit(),
                icon: Store,
            },
            {
                title: 'Products',
                href: sellerProductsIndex(),
                icon: Package,
            },
            {
                title: 'Inventory & CSV',
                href: sellerInventoryIndex(),
                icon: Boxes,
            },
            {
                title: 'Orders & Fulfillment',
                href: sellerOrdersIndex(),
                icon: ClipboardList,
            },
            {
                title: 'Finance & Payouts',
                href: sellerFinanceIndex(),
                icon: Wallet,
            },
        );
    }

    items.push({
        title: isApproved.value ? 'Store Verification' : 'Seller Onboarding',
        href: sellerOnboarding(),
        icon: ShieldCheck,
    });

    items.push({
        title: 'Account & Security',
        href: profileEdit(),
        icon: Settings,
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
                        <Link :href="sellerHomeHref">
                            <BrandLogo
                                name="XHopy"
                                subtitle="Seller Portal"
                                size="sm"
                                class="truncate"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div class="px-2 group-data-[collapsible=icon]:hidden">
                <Badge
                    :variant="isApproved ? 'default' : 'secondary'"
                    class="w-full justify-center py-0.5 text-[11px] font-semibold"
                >
                    {{ isApproved ? 'Verified Store' : 'Verification Pending' }}
                </Badge>
            </div>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain label="Store Workspace" :items="storeNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        tooltip="Customer Account"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <Link :href="dashboard()">
                            <LayoutGrid />
                            <span>Customer Account</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        tooltip="Public Storefront"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <Link :href="home()">
                            <Store />
                            <span>Storefront</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
