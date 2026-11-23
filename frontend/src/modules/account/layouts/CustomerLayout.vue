<script setup lang="ts">
import CustomerSidebar from '@modules/account/components/CustomerSidebar.vue';
import AppContent from '@shared/components/AppContent.vue';
import AppShell from '@shared/components/AppShell.vue';
import PortalProgressBar from '@shared/components/PortalProgressBar.vue';
import PortalShellHeader from '@shared/components/PortalShellHeader.vue';
import { Toaster } from '@shared/components/ui/sonner';
import type { BreadcrumbItem } from '@shared/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <PortalProgressBar />
    <AppShell variant="sidebar">
        <CustomerSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <PortalShellHeader
                portal-label="Customer Portal"
                portal-tone="customer"
                :breadcrumbs="breadcrumbs"
            />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
