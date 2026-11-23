<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Download,
    FileText,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminOperationsTabs from '@modules/admin/components/AdminOperationsTabs.vue';
import InputError from '@shared/components/InputError.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Label } from '@shared/components/ui/label';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { download as downloadSellerDocument } from '@/routes/admin/sellers/documents';
import { update as transitionSellerStatus } from '@/routes/admin/sellers/status';
import type {
    AdminSellerDetail,
    AdminSellerTransitionOption,
} from '@modules/admin/types';

const props = defineProps<{
    seller: AdminSellerDetail;
    allowedTransitions: AdminSellerTransitionOption[];
    canModerate: boolean;
}>();

const selectedReason = ref('');

const transitionForm = useForm({
    status: '',
    reason: '',
});

const requiresReasonForAny = computed(() =>
    props.allowedTransitions.some((item) => item.requires_reason),
);

function submitTransition(targetStatus: string): void {
    transitionForm.status = targetStatus;
    transitionForm.reason = selectedReason.value.trim();

    transitionForm.patch(transitionSellerStatus.url(props.seller.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedReason.value = '';
        },
    });
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
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
    <Head :title="`${seller.business_name} — Seller Review`" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <AdminOperationsTabs active="sellers" />

        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <Button
                    as-child
                    variant="ghost"
                    size="sm"
                    class="-ml-2 h-8 gap-1.5 text-muted-foreground"
                >
                    <Link :href="adminSellersIndex()">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        <span>Back to Seller Queue</span>
                    </Link>
                </Button>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Building2 class="size-6 text-primary" aria-hidden="true" />
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ seller.store?.name ?? seller.business_name }}
                    </h1>
                    <Badge :variant="statusBadgeVariant(seller.status)">
                        {{ seller.status_label }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ seller.business_name }} · Applicant:
                    {{ seller.applicant.name }} ({{ seller.applicant.email }})
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Store &amp; Business Profile</CardTitle>
                        <CardDescription>
                            Submitted seller registration details and storefront
                            metadata.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Store Name
                            </p>
                            <p class="font-medium text-foreground">
                                {{ seller.store?.name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Store Handle &amp; Status
                            </p>
                            <p class="font-mono text-xs text-foreground">
                                /{{ seller.store?.slug ?? '—' }} ·
                                {{ seller.store?.status_label ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Registered Business Name
                            </p>
                            <p class="font-medium text-foreground">
                                {{ seller.business_name }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Business Type
                            </p>
                            <p class="font-medium text-foreground">
                                {{ seller.business_type }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Contact Phone
                            </p>
                            <p class="font-medium text-foreground">
                                {{ seller.contact_phone }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Tax Identifier
                            </p>
                            <p class="font-medium text-foreground">
                                {{ seller.tax_identifier || 'Not provided' }}
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs text-muted-foreground">
                                Business Address
                            </p>
                            <p class="font-medium text-foreground">
                                {{ seller.business_address }}
                            </p>
                        </div>
                        <div
                            v-if="seller.store?.description"
                            class="sm:col-span-2"
                        >
                            <p class="text-xs text-muted-foreground">
                                Store Description
                            </p>
                            <p class="text-foreground">
                                {{ seller.store.description }}
                            </p>
                        </div>
                        <div v-if="seller.payout_notes" class="sm:col-span-2">
                            <p class="text-xs text-muted-foreground">
                                Settlement Notes
                            </p>
                            <p class="text-foreground">
                                {{ seller.payout_notes }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <FileText
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                >Verification Documents ({{
                                    seller.documents.length
                                }})</span
                            >
                        </CardTitle>
                        <CardDescription>
                            Files are stored in private storage. Every access is
                            audited.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            v-if="seller.documents.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No verification documents uploaded yet.
                        </p>
                        <ul v-else class="divide-y divide-border">
                            <li
                                v-for="doc in seller.documents"
                                :key="doc.id"
                                class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"
                            >
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{ doc.original_filename }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ doc.document_type_label }} ·
                                        {{ doc.mime_type }} ·
                                        {{ formatBytes(doc.size_bytes) }} ·
                                        Uploaded {{ doc.created_at_diff }}
                                    </p>
                                </div>
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="h-9 gap-1.5"
                                >
                                    <a
                                        :href="
                                            downloadSellerDocument.url({
                                                sellerProfile: seller.id,
                                                document: doc.id,
                                            })
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Download
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span>Download</span>
                                    </a>
                                </Button>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Status Audit History</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-3 text-xs">
                            <li
                                v-for="entry in seller.status_history"
                                :key="entry.id"
                                class="border-l-2 border-primary/40 pl-3"
                            >
                                <p class="font-semibold text-foreground">
                                    {{
                                        entry.from_status
                                            ? `${entry.from_status} -> ${entry.to_status}`
                                            : entry.to_status
                                    }}
                                </p>
                                <p
                                    v-if="entry.reason"
                                    class="mt-0.5 text-muted-foreground"
                                >
                                    {{ entry.reason }}
                                </p>
                                <p class="mt-0.5 text-muted-foreground">
                                    {{ entry.actor_name }} ·
                                    {{ entry.created_at }}
                                </p>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <ShieldCheck
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Decision Controls</span>
                        </CardTitle>
                        <CardDescription>
                            {{
                                canModerate
                                    ? 'Super Admin approval activates the store and unlocks the Seller Dashboard.'
                                    : 'Reviewers can triage pending applications to Under Review. Final approval requires a Super Admin.'
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Alert
                            v-if="seller.rejection_reason"
                            variant="destructive"
                        >
                            <AlertTitle>Latest Review Reason</AlertTitle>
                            <AlertDescription>
                                {{ seller.rejection_reason }}
                            </AlertDescription>
                        </Alert>

                        <div
                            v-if="allowedTransitions.length === 0"
                            class="rounded-lg border border-dashed border-border p-3 text-xs text-muted-foreground"
                        >
                            No status transitions are available for your account
                            at this stage.
                        </div>

                        <template v-else>
                            <div
                                v-if="requiresReasonForAny || canModerate"
                                class="space-y-1.5"
                            >
                                <Label for="moderation_reason"
                                    >Review / Moderation Note</Label
                                >
                                <textarea
                                    id="moderation_reason"
                                    v-model="selectedReason"
                                    rows="3"
                                    placeholder="Required when rejecting or suspending an application..."
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="
                                        transitionForm.errors.status ??
                                        transitionForm.errors.reason
                                    "
                                />
                            </div>

                            <div class="flex flex-col gap-2">
                                <Button
                                    v-for="transition in allowedTransitions"
                                    :key="transition.value"
                                    type="button"
                                    :variant="
                                        transition.value === 'approved'
                                            ? 'default'
                                            : transition.value === 'rejected' ||
                                                transition.value === 'suspended'
                                              ? 'destructive'
                                              : 'secondary'
                                    "
                                    class="h-10 w-full"
                                    :disabled="transitionForm.processing"
                                    @click="submitTransition(transition.value)"
                                >
                                    {{ transition.label }}
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
