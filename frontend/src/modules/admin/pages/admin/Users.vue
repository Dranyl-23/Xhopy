<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { History, Search, ShieldCheck, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { PaginationBar } from '@modules/catalog';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { SelectField } from '@shared/components/ui/select';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { update as updateUserRole } from '@/routes/admin/users/role';
import type {
    AdminAuditLogRow,
    AdminStatusOption,
    AdminUserRow,
} from '@modules/admin/types';

const props = defineProps<{
    users: {
        data: AdminUserRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    recentAuditLogs: AdminAuditLogRow[];
    roles: AdminStatusOption[];
    filters: {
        q: string;
        role: string;
    };
}>();

const page = usePage();
const searchInput = ref(props.filters.q);
const roleFilter = ref(props.filters.role);

const paginationMeta = computed(() => ({
    currentPage: props.users.current_page,
    lastPage: props.users.last_page,
    perPage: props.users.per_page,
    total: props.users.total,
}));

function applyFilter(pageNumber = 1): void {
    router.get(
        adminUsersIndex.url({
            query: {
                q: searchInput.value.trim() || undefined,
                role: roleFilter.value || undefined,
                page: pageNumber > 1 ? pageNumber : undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function handleRoleChange(targetUser: AdminUserRow, nextRole: string): void {
    if (nextRole === '' || nextRole === targetUser.role) {
        return;
    }

    router.patch(
        updateUserRole.url(targetUser.id),
        { role: nextRole },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Users & Roles — Admin" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div>
            <h1
                class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
            >
                <Users class="size-6 text-primary" aria-hidden="true" />
                <span>Users &amp; Role Authorization</span>
            </h1>
            <p class="text-sm text-muted-foreground">
                Enforce least-privilege access across customer, staff, and
                administrator accounts and inspect security audit events.
            </p>
        </div>

        <form
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
            @submit.prevent="applyFilter()"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    v-model="searchInput"
                    placeholder="Search user name or email..."
                    aria-label="Search users by name or email"
                    class="h-11 pl-9"
                />
            </div>

            <SelectField
                v-model="roleFilter"
                :options="roles"
                empty-option-label="All Roles"
                aria-label="Filter users by role"
                trigger-class="sm:w-[175px]"
                @change="applyFilter()"
            />

            <Button type="submit" variant="outline" class="h-11">
                Filter
            </Button>
        </form>

        <Card>
            <CardHeader>
                <CardTitle>Accounts ({{ users.total }})</CardTitle>
                <CardDescription>
                    Updating a user's role revokes active sessions for that
                    account so new privileges take effect cleanly.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-y border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Account</th>
                                <th class="px-4 py-3">Security Posture</th>
                                <th class="px-4 py-3">Orders &amp; Spend</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="account in users.data"
                                :key="account.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3.5">
                                    <p class="font-medium text-foreground">
                                        {{ account.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ account.email }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        <Badge
                                            :variant="
                                                account.email_verified
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                        >
                                            {{
                                                account.email_verified
                                                    ? 'Verified'
                                                    : 'Unverified'
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="
                                                account.has_two_factor ||
                                                account.has_passkeys
                                            "
                                            variant="outline"
                                            class="gap-1"
                                        >
                                            <ShieldCheck
                                                class="size-3 text-green-600"
                                                aria-hidden="true"
                                            />
                                            <span>MFA Enabled</span>
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-xs">
                                    <p
                                        class="font-semibold text-foreground tabular-nums"
                                    >
                                        {{ account.orders_count ?? 0 }}
                                        {{
                                            (account.orders_count ?? 0) === 1
                                                ? 'order'
                                                : 'orders'
                                        }}
                                    </p>
                                    <p
                                        class="text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            account.total_spent_formatted ??
                                            '₱0.00'
                                        }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="w-40">
                                        <SelectField
                                            :model-value="account.role"
                                            :options="roles"
                                            size="default"
                                            :disabled="
                                                account.id ===
                                                page.props.auth.user.id
                                            "
                                            :aria-label="`Role for ${account.name}`"
                                            @change="
                                                (value) =>
                                                    handleRoleChange(
                                                        account,
                                                        String(value),
                                                    )
                                            "
                                        />
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ account.created_at_diff }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <PaginationBar
            :meta="paginationMeta"
            @change-page="(nextPage) => applyFilter(nextPage)"
        />

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <History class="size-4 text-primary" aria-hidden="true" />
                    <span>Security Audit Trail</span>
                </CardTitle>
                <CardDescription>
                    Most recent identity, verification, role transition, and
                    session events across the platform.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-y border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Event</th>
                                <th class="px-4 py-3">Target Account</th>
                                <th class="px-4 py-3">Actor</th>
                                <th class="px-4 py-3">IP Address</th>
                                <th class="px-4 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="log in recentAuditLogs"
                                :key="log.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ log.label }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ log.user_name }}
                                    <span
                                        v-if="log.user_email"
                                        class="block text-xs"
                                        >{{ log.user_email }}</span
                                    >
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ log.actor_name }}
                                </td>
                                <td
                                    class="px-4 py-3 font-mono text-xs text-muted-foreground"
                                >
                                    {{ log.ip_address }}
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    {{ log.created_at_diff }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
