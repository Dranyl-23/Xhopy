<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminOperationsTabs from '@modules/admin/components/AdminOperationsTabs.vue';
import { PaginationBar } from '@modules/catalog';
import EmptyState from '@shared/components/EmptyState.vue';
import LoadingState from '@shared/components/LoadingState.vue';
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
import {
    index as adminSellersIndex,
    show as adminSellerShow,
} from '@/routes/admin/sellers';
import type {
    AdminSellerListRow,
    AdminSellerSummary,
    AdminStatusOption,
} from '@modules/admin/types';

const props = defineProps<{
    sellers: {
        data: AdminSellerListRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    summary: AdminSellerSummary;
    statuses: AdminStatusOption[];
    canModerate: boolean;
    filters: {
        q: string;
        status: string;
    };
}>();

const searchInput = ref(props.filters.q);
const statusFilter = ref(props.filters.status);
const isLoading = ref(false);

const paginationMeta = computed(() => ({
    currentPage: props.sellers.current_page,
    lastPage: props.sellers.last_page,
    perPage: props.sellers.per_page,
    total: props.sellers.total,
}));

function applyFilter(pageNumber = 1): void {
    isLoading.value = true;
    router.get(
        adminSellersIndex.url({
            query: {
                q: searchInput.value.trim() || undefined,
                status: statusFilter.value || undefined,
                page: pageNumber > 1 ? pageNumber : undefined,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}

function selectStatusPill(status: string): void {
    statusFilter.value = statusFilter.value === status ? '' : status;
    applyFilter(1);
}

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'approved':
            return 'default';
        case 'rejected':
        case 'suspended':
            return 'destructive';
        default:
            return 'secondary';
    }
}
</script>

<template>
    <Head title="Seller Applications — Admin" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <AdminOperationsTabs active="sellers" />

        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                >
                    <Building2 class="size-6 text-primary" aria-hidden="true" />
                    <span>Seller Onboarding Queue</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Inspect applicant stores, verify uploaded documents, and
                    {{
                        canModerate
                            ? 'approve, reject, or suspend marketplace sellers.'
                            : 'mark applications ready for Super Admin moderation.'
                    }}
                </p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <button
                type="button"
                class="rounded-xl border border-border bg-card p-4 text-left transition-colors hover:bg-muted/40"
                @click="selectStatusPill('pending')"
            >
                <p class="text-xs text-muted-foreground">Pending Review</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ summary.pending }}
                </p>
            </button>
            <button
                type="button"
                class="rounded-xl border border-border bg-card p-4 text-left transition-colors hover:bg-muted/40"
                @click="selectStatusPill('under_review')"
            >
                <p class="text-xs text-muted-foreground">Under Review</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ summary.under_review }}
                </p>
            </button>
            <button
                type="button"
                class="rounded-xl border border-border bg-card p-4 text-left transition-colors hover:bg-muted/40"
                @click="selectStatusPill('approved')"
            >
                <p class="text-xs text-muted-foreground">Approved Stores</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ summary.approved }}
                </p>
            </button>
            <button
                type="button"
                class="rounded-xl border border-border bg-card p-4 text-left transition-colors hover:bg-muted/40"
                @click="selectStatusPill('rejected')"
            >
                <p class="text-xs text-muted-foreground">Rejected</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ summary.rejected }}
                </p>
            </button>
            <button
                type="button"
                class="rounded-xl border border-border bg-card p-4 text-left transition-colors hover:bg-muted/40"
                @click="selectStatusPill('suspended')"
            >
                <p class="text-xs text-muted-foreground">Suspended</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">
                    {{ summary.suspended }}
                </p>
            </button>
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
                    placeholder="Search store name, business, or applicant email..."
                    aria-label="Search seller applications"
                    class="h-11 pl-9"
                />
            </div>

            <SelectField
                v-model="statusFilter"
                :options="statuses"
                empty-option-label="All Statuses"
                aria-label="Filter applications by status"
                trigger-class="sm:w-[190px]"
                @change="applyFilter()"
            />

            <Button type="submit" variant="outline" class="h-11">
                Filter
            </Button>
        </form>

        <Card>
            <CardHeader>
                <CardTitle>Seller Applications ({{ sellers.total }})</CardTitle>
                <CardDescription>
                    All document downloads and status changes are recorded in
                    the security audit trail.
                </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="isLoading" class="p-6">
                    <LoadingState
                        :count="4"
                        label="Loading seller applications..."
                    />
                </div>
                <div v-else-if="sellers.data.length === 0" class="p-6">
                    <EmptyState
                        :icon="Building2"
                        title="No seller applications found"
                        description="No seller stores match your search query or status filter."
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-y border-border bg-muted/50 text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Store</th>
                                <th class="px-4 py-3">
                                    Business &amp; Applicant
                                </th>
                                <th class="px-4 py-3">Documents</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Submitted</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="row in sellers.data"
                                :key="row.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-foreground">
                                        {{
                                            row.store?.name ?? row.business_name
                                        }}
                                    </p>
                                    <p
                                        v-if="row.store"
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        /{{ row.store.slug }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-medium text-foreground">
                                        {{ row.business_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ row.applicant.name }} ·
                                        {{ row.applicant.email }}
                                    </p>
                                </td>
                                <td class="px-4 py-3.5 tabular-nums">
                                    {{ row.documents_count }}
                                    {{
                                        row.documents_count === 1
                                            ? 'file'
                                            : 'files'
                                    }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <Badge
                                        :variant="
                                            statusBadgeVariant(row.status)
                                        "
                                    >
                                        {{ row.status_label }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-4 py-3.5 text-xs text-muted-foreground"
                                >
                                    {{ row.submitted_at_diff || '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="h-9"
                                    >
                                        <Link :href="adminSellerShow(row.id)">
                                            Inspect
                                        </Link>
                                    </Button>
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
    </div>
</template>
