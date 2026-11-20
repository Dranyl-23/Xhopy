<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    History,
    KeyRound,
    MapPin,
    PackageCheck,
    ReceiptText,
    ShieldAlert,
    ShieldCheck,
    ShoppingBag,
    Store,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
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
import { index as addressesIndex } from '@/routes/addresses';
import { index as adminCatalogIndex } from '@/routes/admin/catalog';
import { index as adminInventoryIndex } from '@/routes/admin/inventory';
import { index as adminOperationsIndex } from '@/routes/admin/operations';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as cartIndex } from '@/routes/cart';
import { index as catalogIndex } from '@/routes/catalog';
import { index as ordersIndex } from '@/routes/orders';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';
import type { AccountDashboardSummary } from '@modules/account/types';

defineProps<{
    accountSummary: AccountDashboardSummary;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight">
                        Welcome back, {{ user.name }}
                    </h1>
                    <Badge variant="secondary">{{
                        accountSummary.roleLabel
                    }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ user.email }}
                    <span v-if="user.phone"> · {{ user.phone }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="h-10 gap-2">
                    <Link :href="home()">
                        <Store class="size-4" aria-hidden="true" />
                        <span>Storefront</span>
                    </Link>
                </Button>
                <Button as-child class="h-10 gap-2">
                    <Link :href="catalogIndex()">
                        <ShoppingBag class="size-4" aria-hidden="true" />
                        <span>Browse Catalog</span>
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="page.props.auth?.mfaRecommended"
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <div class="flex items-start gap-3">
                <ShieldAlert
                    class="mt-0.5 size-5 shrink-0 text-amber-600"
                    aria-hidden="true"
                />
                <div>
                    <p class="font-semibold">
                        Protect your staff/admin account with Multi-Factor
                        Authentication
                    </p>
                    <p class="text-xs opacity-90">
                        Enable an authenticator app or register a passkey in
                        Security settings.
                    </p>
                </div>
            </div>
            <Button as-child variant="outline" size="sm" class="h-9">
                <Link :href="securityEdit()">Configure MFA</Link>
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Account Security</CardDescription>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <ShieldCheck
                            v-if="accountSummary.hasMfaEnabled"
                            class="size-5 text-green-600"
                            aria-hidden="true"
                        />
                        <KeyRound
                            v-else
                            class="size-5 text-amber-600"
                            aria-hidden="true"
                        />
                        <span>
                            {{
                                accountSummary.hasMfaEnabled
                                    ? 'MFA Protected'
                                    : 'Standard Sign-In'
                            }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <p>
                        Email status:
                        <span class="font-medium text-foreground">
                            {{
                                accountSummary.emailVerified
                                    ? 'Verified'
                                    : 'Pending Verification'
                            }}
                        </span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-9"
                        >
                            <Link :href="profileEdit()">Edit Profile</Link>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-9"
                        >
                            <Link :href="securityEdit()"
                                >Security &amp; Sessions</Link
                            >
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Address Book</CardDescription>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <MapPin
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            {{ accountSummary.addressCount }}
                            {{
                                accountSummary.addressCount === 1
                                    ? 'Saved Address'
                                    : 'Saved Addresses'
                            }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <p v-if="accountSummary.defaultShippingAddress">
                        Default shipping:
                        <span class="font-medium text-foreground">
                            {{ accountSummary.defaultShippingAddress.label }} —
                            {{
                                accountSummary.defaultShippingAddress
                                    .recipient_name
                            }}
                        </span>
                    </p>
                    <p v-else>
                        Add a shipping address for faster checkout in upcoming
                        batches.
                    </p>
                    <Button as-child variant="outline" size="sm" class="h-9">
                        <Link :href="addressesIndex()">Manage Addresses</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Orders &amp; Checkout</CardDescription>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <ReceiptText
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>
                            {{ accountSummary.orderCount }}
                            {{
                                accountSummary.orderCount === 1
                                    ? 'Order'
                                    : 'Orders'
                            }}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <p>
                        Track PayMongo payment confirmations, order status
                        history, and active shopping cart items.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child size="sm" class="h-9 gap-1.5">
                            <Link :href="ordersIndex()">
                                <span>View My Orders</span>
                                <Badge
                                    v-if="
                                        (accountSummary.unreadNotificationsCount ??
                                            0) > 0
                                    "
                                    variant="secondary"
                                    class="ml-0.5 px-1.5 py-0 text-[10px]"
                                >
                                    {{
                                        accountSummary.unreadNotificationsCount
                                    }}
                                </Badge>
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="h-9"
                        >
                            <Link :href="cartIndex()">Open Cart</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card
            v-if="
                page.props.auth?.canManageCatalog ||
                page.props.auth?.canManageUsers
            "
        >
            <CardHeader class="pb-2">
                <CardDescription>Administration</CardDescription>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <PackageCheck
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                    <span>Workspace Shortcuts</span>
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Button
                    v-if="page.props.auth?.canManageOperations"
                    as-child
                    size="sm"
                    class="h-9"
                >
                    <Link :href="adminOperationsIndex()"
                        >Operations &amp; Analytics</Link
                    >
                </Button>
                <Button
                    v-if="page.props.auth?.canManageOperations"
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9"
                >
                    <Link :href="adminOrdersIndex()">Admin Orders</Link>
                </Button>
                <Button
                    v-if="page.props.auth?.canManageOperations"
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9"
                >
                    <Link :href="adminInventoryIndex()">Inventory</Link>
                </Button>
                <Button
                    v-if="page.props.auth?.canManageCatalog"
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9"
                >
                    <Link :href="adminCatalogIndex()">Catalog Admin</Link>
                </Button>
                <Button
                    v-if="page.props.auth?.canManageUsers"
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5"
                >
                    <Link :href="adminUsersIndex()">
                        <Users class="size-3.5" aria-hidden="true" />
                        <span>Users &amp; Roles</span>
                    </Link>
                </Button>
                <Button as-child variant="outline" size="sm" class="h-9">
                    <Link :href="catalogIndex()">Explore Catalog</Link>
                </Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <History
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span>Recent Security Activity</span>
                </CardTitle>
                <CardDescription>
                    Latest account events recorded for your sign-ins and profile
                    security.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="accountSummary.recentSecurityEvents.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No recent security events yet.
                </p>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="entry in accountSummary.recentSecurityEvents"
                        :key="entry.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm"
                    >
                        <span class="font-medium text-foreground">{{
                            entry.label
                        }}</span>
                        <span class="text-xs text-muted-foreground">
                            IP {{ entry.ip_address }} ·
                            {{ entry.created_at_diff }}
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
