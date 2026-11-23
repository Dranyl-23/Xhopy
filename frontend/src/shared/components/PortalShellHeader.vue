<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Store } from '@lucide/vue';
import Breadcrumbs from '@shared/components/Breadcrumbs.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { SidebarTrigger } from '@shared/components/ui/sidebar';
import { home } from '@/routes';
import type { BreadcrumbItem } from '@shared/types';

withDefaults(
    defineProps<{
        portalLabel: string;
        portalTone?: 'admin' | 'seller' | 'customer';
        breadcrumbs?: BreadcrumbItem[];
        showStorefrontLink?: boolean;
    }>(),
    {
        portalTone: 'customer',
        breadcrumbs: () => [],
        showStorefrontLink: true,
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border/70 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 sm:px-6"
    >
        <div class="flex min-w-0 items-center gap-2.5">
            <SidebarTrigger class="-ml-1 shrink-0" />

            <Badge
                :variant="
                    portalTone === 'admin'
                        ? 'default'
                        : portalTone === 'seller'
                          ? 'secondary'
                          : 'outline'
                "
                class="shrink-0 text-[11px] font-semibold tracking-wide"
            >
                {{ portalLabel }}
            </Badge>

            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs
                    :breadcrumbs="breadcrumbs"
                    class="hidden min-w-0 sm:flex"
                />
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <slot name="actions" />

            <Button
                v-if="showStorefrontLink"
                as-child
                variant="ghost"
                size="sm"
                class="h-9 gap-1.5 text-xs"
            >
                <Link :href="home()">
                    <Store class="size-3.5" aria-hidden="true" />
                    <span class="hidden sm:inline">Storefront</span>
                </Link>
            </Button>
        </div>
    </header>
</template>
