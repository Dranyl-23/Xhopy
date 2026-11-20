<script setup lang="ts">
import AppContent from '@shared/components/AppContent.vue';
import AppShell from '@shared/components/AppShell.vue';
import AppSidebar from '@shared/components/AppSidebar.vue';
import AppSidebarHeader from '@shared/components/AppSidebarHeader.vue';
import { Toaster } from '@shared/components/ui/sonner';
import type { BreadcrumbItem } from '@shared/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
