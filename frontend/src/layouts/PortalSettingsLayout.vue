<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CustomerLayout, SettingsLayout } from '@modules/account';
import { AdminLayout } from '@modules/admin';
import { SellerLayout } from '@modules/seller';
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

const portalLayout = computed(() => {
    const auth = page.props.auth;
    if (auth?.canAccessAdmin) {
        return AdminLayout;
    }
    if (auth?.isSeller) {
        return SellerLayout;
    }

    return CustomerLayout;
});
</script>

<template>
    <component :is="portalLayout" :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <slot />
        </SettingsLayout>
    </component>
</template>
