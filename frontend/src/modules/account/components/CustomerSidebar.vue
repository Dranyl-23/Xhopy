<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Heart,
    LayoutGrid,
    MapPin,
    ReceiptText,
    Settings,
    ShoppingBag,
    Store,
} from '@lucide/vue';
import { computed } from 'vue';
import BrandLogo from '@shared/components/BrandLogo.vue';
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
import { index as catalogIndex } from '@/routes/catalog';
import { index as ordersIndex } from '@/routes/orders';
import { edit as profileEdit } from '@/routes/profile';
import {
    dashboard as sellerDashboard,
    onboarding as sellerOnboarding,
} from '@/routes/seller';
import { index as wishlistIndex } from '@/routes/wishlist';
import type { NavItem } from '@shared/types';

const page = usePage();

const shoppingNavItems: NavItem[] = [
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
        title: 'Wishlist',
        href: wishlistIndex(),
        icon: Heart,
    },
    {
        title: 'Browse Catalog',
        href: catalogIndex(),
        icon: ShoppingBag,
    },
    {
        title: 'Storefront',
        href: home(),
        icon: Store,
    },
];

const accountNavItems: NavItem[] = [
    {
        title: 'Saved Addresses',
        href: addressesIndex(),
        icon: MapPin,
    },
    {
        title: 'Profile & Security',
        href: profileEdit(),
        icon: Settings,
    },
];

const showSellerSwitch = computed(
    () => page.props.auth?.user && !page.props.auth?.canAccessAdmin,
);

const sellerHref = computed(() =>
    page.props.auth?.isApprovedSeller ? sellerDashboard() : sellerOnboarding(),
);

const sellerLabel = computed(() =>
    page.props.auth?.isApprovedSeller
        ? 'Seller Dashboard'
        : page.props.auth?.isSeller
          ? 'Seller Onboarding'
          : 'Sell on XHopy',
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <BrandLogo
                                name="XHopy"
                                subtitle="Customer Portal"
                                size="sm"
                                class="truncate"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain
                label="Shopping &amp; Activity"
                :items="shoppingNavItems"
            />
            <NavMain label="Account &amp; Delivery" :items="accountNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu v-if="showSellerSwitch">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        :tooltip="sellerLabel"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        <Link :href="sellerHref">
                            <Building2 />
                            <span>{{ sellerLabel }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
