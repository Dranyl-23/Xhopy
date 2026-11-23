<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ShieldAlert, ShoppingBag } from '@lucide/vue';
import { computed } from 'vue';
import BrandLogo from '@shared/components/BrandLogo.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { dashboard, home } from '@/routes';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { index as catalogIndex } from '@/routes/catalog';
import {
    dashboard as sellerDashboard,
    onboarding as sellerOnboarding,
} from '@/routes/seller';

const props = defineProps<{
    status: number;
}>();

const page = usePage();

const copy = computed(() => {
    switch (props.status) {
        case 403:
            return {
                badge: '403 Unauthorized',
                title: 'Access restricted to authorized accounts',
                description:
                    'Your current portal role or store permissions do not allow access to this workspace or resource.',
            };
        case 404:
            return {
                badge: '404 Not Found',
                title: 'We could not find that page',
                description:
                    'The requested XHopy page or record may have moved, been removed, or is no longer available.',
            };
        case 503:
            return {
                badge: '503 Unavailable',
                title: 'XHopy is briefly under maintenance',
                description:
                    'We are updating platform services right now. Please try again in a few moments.',
            };
        default:
            return {
                badge: `${props.status} Server Error`,
                title: 'Something went wrong on our end',
                description:
                    'An unexpected error occurred while processing your request. Please return to your portal and try again.',
            };
    }
});

const portalReturnTarget = computed(() => {
    const auth = page.props.auth;
    if (!auth?.user) {
        return { label: 'Return to Storefront', href: home() };
    }
    if (auth.canManageOperations) {
        return { label: 'Admin Operations', href: adminOperationsIndex() };
    }
    if (auth.canReviewSellers) {
        return { label: 'Seller Queue', href: adminSellersIndex() };
    }
    if (auth.canManageCatalog) {
        return { label: 'Catalog Admin', href: adminCatalogIndex() };
    }
    if (auth.isApprovedSeller) {
        return { label: 'Seller Dashboard', href: sellerDashboard() };
    }
    if (auth.isSeller) {
        return { label: 'Seller Onboarding', href: sellerOnboarding() };
    }

    return { label: 'My Account Dashboard', href: dashboard() };
});
</script>

<template>
    <Head :title="`${copy.badge} — XHopy`" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-muted/30 px-4 py-12"
    >
        <div class="mb-6">
            <Link :href="home()">
                <BrandLogo
                    name="XHopy"
                    size="lg"
                    subtitle="Official Marketplace"
                />
            </Link>
        </div>

        <Card class="w-full max-w-lg shadow-sm">
            <CardHeader class="space-y-3 text-center">
                <div class="mx-auto flex items-center justify-center gap-2">
                    <div
                        class="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                    >
                        <ShieldAlert class="size-5" aria-hidden="true" />
                    </div>
                </div>
                <div class="flex justify-center">
                    <Badge variant="destructive">{{ copy.badge }}</Badge>
                </div>
                <CardTitle class="text-xl">{{ copy.title }}</CardTitle>
                <CardDescription class="text-sm leading-relaxed">
                    {{ copy.description }}
                </CardDescription>
            </CardHeader>

            <CardContent
                class="flex flex-col justify-center gap-2.5 sm:flex-row"
            >
                <Button as-child class="h-11 gap-2">
                    <Link :href="portalReturnTarget.href">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        <span>{{ portalReturnTarget.label }}</span>
                    </Link>
                </Button>
                <Button as-child variant="outline" class="h-11 gap-2">
                    <Link :href="catalogIndex()">
                        <ShoppingBag class="size-4" aria-hidden="true" />
                        <span>Browse Catalog</span>
                    </Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
