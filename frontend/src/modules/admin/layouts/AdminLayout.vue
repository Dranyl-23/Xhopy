<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminSidebar from '@modules/admin/components/AdminSidebar.vue';
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

const page = usePage();

const portalLabel = computed(() =>
    page.props.auth?.canManageUsers
        ? 'Super Admin Portal'
        : 'Admin Staff Portal',
);
</script>

<template>
    <PortalProgressBar />
    <AppShell variant="sidebar">
        <AdminSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <PortalShellHeader
                :portal-label="portalLabel"
                portal-tone="admin"
                :breadcrumbs="breadcrumbs"
            />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
