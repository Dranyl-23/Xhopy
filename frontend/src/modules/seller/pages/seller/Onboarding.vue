<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Clock3,
    Download,
    FileText,
    ShieldCheck,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { SelectField } from '@shared/components/ui/select';
import { dashboard } from '@/routes';
import { dashboard as sellerDashboard } from '@/routes/seller';
import { store as saveSellerApplication } from '@/routes/seller/onboarding';
import {
    destroy as destroyDocument,
    download as downloadDocument,
    store as uploadSellerDocument,
} from '@/routes/seller/onboarding/documents';
import type {
    SellerOnboardingProfile,
    SellerSelectOption,
} from '@modules/seller/types';

const props = defineProps<{
    profile: SellerOnboardingProfile | null;
    documentTypes: SellerSelectOption[];
    businessTypes: SellerSelectOption[];
    defaultContactEmail: string;
    defaultContactPhone: string;
}>();

const canEdit = computed(() => props.profile?.can_edit ?? true);

const applicationForm = useForm({
    store_name: props.profile?.store?.name ?? '',
    store_slug: props.profile?.store?.slug ?? '',
    store_description: props.profile?.store?.description ?? '',
    contact_email:
        props.profile?.store?.contact_email ?? props.defaultContactEmail,
    contact_phone: props.profile?.contact_phone ?? props.defaultContactPhone,
    business_name: props.profile?.business_name ?? '',
    business_type: props.profile?.business_type ?? 'sole_proprietorship',
    business_address: props.profile?.business_address ?? '',
    tax_identifier: props.profile?.tax_identifier ?? '',
    payout_notes: props.profile?.payout_notes ?? '',
});

const selectedDocumentType = ref(
    props.documentTypes[0]?.value ?? 'government_id',
);
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const documentUploading = ref(false);
const documentError = ref<string | null>(null);

function submitApplication(): void {
    applicationForm.post(saveSellerApplication.url(), {
        preserveScroll: true,
    });
}

function handleFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    selectedFile.value = input?.files?.[0] ?? null;
    documentError.value = null;
}

function submitDocument(): void {
    if (!selectedFile.value) {
        documentError.value = 'Choose a verification document file to upload.';
        return;
    }

    documentUploading.value = true;
    documentError.value = null;

    router.post(
        uploadSellerDocument.url(),
        {
            document_type: selectedDocumentType.value,
            document: selectedFile.value,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                documentError.value =
                    errors.document ??
                    errors.document_type ??
                    'Unable to upload document.';
            },
            onSuccess: () => {
                selectedFile.value = null;
                if (fileInputRef.value) {
                    fileInputRef.value.value = '';
                }
            },
            onFinish: () => {
                documentUploading.value = false;
            },
        },
    );
}

function removeDocument(documentId: number): void {
    router.delete(destroyDocument.url(documentId), {
        preserveScroll: true,
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
    status?: string,
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
    <Head title="Seller Onboarding &amp; Store Verification" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                >
                    <Building2 class="size-6 text-primary" aria-hidden="true" />
                    <span>Seller Onboarding &amp; Verification</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Register your independent store profile and upload private
                    verification documents for Super Admin approval.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="h-10">
                    <Link :href="dashboard()">Back to Account</Link>
                </Button>
                <Button
                    v-if="profile?.is_approved"
                    as-child
                    class="h-10 gap-1.5"
                >
                    <Link :href="sellerDashboard()">
                        <ShieldCheck class="size-4" aria-hidden="true" />
                        <span>Open Seller Dashboard</span>
                    </Link>
                </Button>
            </div>
        </div>

        <Alert
            v-if="profile"
            :variant="
                profile.status === 'rejected' || profile.status === 'suspended'
                    ? 'destructive'
                    : 'default'
            "
        >
            <CheckCircle2
                v-if="profile.status === 'approved'"
                class="size-4"
                aria-hidden="true"
            />
            <AlertCircle
                v-else-if="
                    profile.status === 'rejected' ||
                    profile.status === 'suspended'
                "
                class="size-4"
                aria-hidden="true"
            />
            <Clock3 v-else class="size-4" aria-hidden="true" />

            <AlertTitle class="flex flex-wrap items-center gap-2">
                <span>Application Status:</span>
                <Badge :variant="statusBadgeVariant(profile.status)">
                    {{ profile.status_label }}
                </Badge>
                <span
                    v-if="profile.store"
                    class="text-xs font-normal text-muted-foreground"
                >
                    Store: {{ profile.store.name }} ({{
                        profile.store.status_label
                    }})
                </span>
            </AlertTitle>

            <AlertDescription class="mt-1 space-y-1.5">
                <p v-if="profile.status === 'approved'">
                    Your seller application and store are approved. Access your
                    seller workspace to review store metrics and readiness.
                </p>
                <p v-else-if="profile.status === 'under_review'">
                    Your application is currently being reviewed by our
                    marketplace team. Form edits are locked until a decision is
                    made.
                </p>
                <p v-else-if="profile.status === 'rejected'">
                    Your application needs updates before approval. Update the
                    fields below and resubmit.
                </p>
                <p v-else-if="profile.status === 'suspended'">
                    Your seller access is currently suspended. Contact
                    marketplace support for reactivation review.
                </p>
                <p v-else>
                    Your application is queued for review. Upload supporting
                    verification documents below if needed.
                </p>
                <p
                    v-if="profile.rejection_reason"
                    class="font-medium text-foreground"
                >
                    Review Note: {{ profile.rejection_reason }}
                </p>
            </AlertDescription>
        </Alert>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Store &amp; Business Details</CardTitle>
                    <CardDescription>
                        Provide accurate legal business information and your
                        public storefront identity.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submitApplication">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="store_name">Store Name</Label>
                                <Input
                                    id="store_name"
                                    v-model="applicationForm.store_name"
                                    placeholder="e.g. Manila Sound Studio"
                                    :disabled="!canEdit"
                                    required
                                    class="h-11"
                                />
                                <InputError
                                    :message="applicationForm.errors.store_name"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="store_slug"
                                    >Store Handle / Slug (optional)</Label
                                >
                                <Input
                                    id="store_slug"
                                    v-model="applicationForm.store_slug"
                                    placeholder="manila-sound-studio"
                                    :disabled="!canEdit"
                                    class="h-11"
                                />
                                <InputError
                                    :message="applicationForm.errors.store_slug"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="store_description"
                                >Store Description</Label
                            >
                            <textarea
                                id="store_description"
                                v-model="applicationForm.store_description"
                                rows="3"
                                :disabled="!canEdit"
                                placeholder="Describe the catalog categories and brands your store specializes in..."
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <InputError
                                :message="
                                    applicationForm.errors.store_description
                                "
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="business_name"
                                    >Registered Business / Full Name</Label
                                >
                                <Input
                                    id="business_name"
                                    v-model="applicationForm.business_name"
                                    placeholder="Manila Sound Ventures OPC"
                                    :disabled="!canEdit"
                                    required
                                    class="h-11"
                                />
                                <InputError
                                    :message="
                                        applicationForm.errors.business_name
                                    "
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="business_type">Business Type</Label>
                                <SelectField
                                    id="business_type"
                                    v-model="applicationForm.business_type"
                                    :options="businessTypes"
                                    :disabled="!canEdit"
                                    aria-label="Select business type"
                                />
                                <InputError
                                    :message="
                                        applicationForm.errors.business_type
                                    "
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="contact_email"
                                    >Store Contact Email</Label
                                >
                                <Input
                                    id="contact_email"
                                    v-model="applicationForm.contact_email"
                                    type="email"
                                    :disabled="!canEdit"
                                    class="h-11"
                                />
                                <InputError
                                    :message="
                                        applicationForm.errors.contact_email
                                    "
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="contact_phone">Contact Phone</Label>
                                <Input
                                    id="contact_phone"
                                    v-model="applicationForm.contact_phone"
                                    placeholder="+63 917 555 0199"
                                    :disabled="!canEdit"
                                    required
                                    class="h-11"
                                />
                                <InputError
                                    :message="
                                        applicationForm.errors.contact_phone
                                    "
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="business_address"
                                >Business / Warehouse Address</Label
                            >
                            <textarea
                                id="business_address"
                                v-model="applicationForm.business_address"
                                rows="2"
                                :disabled="!canEdit"
                                required
                                placeholder="Unit, street, barangay, city, and postal code"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <InputError
                                :message="
                                    applicationForm.errors.business_address
                                "
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="tax_identifier"
                                    >Tax / Business ID (optional)</Label
                                >
                                <Input
                                    id="tax_identifier"
                                    v-model="applicationForm.tax_identifier"
                                    placeholder="TIN or registration reference"
                                    :disabled="!canEdit"
                                    class="h-11"
                                />
                                <InputError
                                    :message="
                                        applicationForm.errors.tax_identifier
                                    "
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="payout_notes"
                                    >Settlement / Payout Notes (optional)</Label
                                >
                                <Input
                                    id="payout_notes"
                                    v-model="applicationForm.payout_notes"
                                    placeholder="Preferred bank/wallet contact note"
                                    :disabled="!canEdit"
                                    class="h-11"
                                />
                                <InputError
                                    :message="
                                        applicationForm.errors.payout_notes
                                    "
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-3 pt-2">
                            <Button
                                v-if="canEdit"
                                type="submit"
                                class="h-11 px-6"
                                :disabled="applicationForm.processing"
                            >
                                {{
                                    profile
                                        ? profile.status === 'rejected'
                                            ? 'Update & Resubmit Application'
                                            : 'Update Application'
                                        : 'Submit Seller Application'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <FileText
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Verification Documents</span>
                        </CardTitle>
                        <CardDescription>
                            Files are stored privately (PDF, JPG, PNG, WebP up
                            to 5 MB) and accessible only to you and authorized
                            reviewers.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-if="!profile"
                            class="rounded-lg border border-dashed border-border p-4 text-xs text-muted-foreground"
                        >
                            Save your store application first to unlock secure
                            document uploads.
                        </div>

                        <form
                            v-else-if="canEdit"
                            class="space-y-3"
                            @submit.prevent="submitDocument"
                        >
                            <div class="space-y-1.5">
                                <Label for="document_type">Document Type</Label>
                                <SelectField
                                    id="document_type"
                                    v-model="selectedDocumentType"
                                    :options="documentTypes"
                                    aria-label="Verification document type"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="verification_document"
                                    >Select File</Label
                                >
                                <input
                                    id="verification_document"
                                    ref="fileInputRef"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    class="block w-full cursor-pointer rounded-md border border-input bg-transparent px-3 py-2 text-xs text-foreground file:mr-3 file:rounded file:border-0 file:bg-secondary file:px-2.5 file:py-1 file:text-xs file:font-medium file:text-secondary-foreground"
                                    @change="handleFileSelect"
                                />
                                <InputError
                                    :message="documentError ?? undefined"
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="secondary"
                                class="h-10 w-full gap-2"
                                :disabled="documentUploading"
                            >
                                <Upload class="size-4" aria-hidden="true" />
                                <span>{{
                                    documentUploading
                                        ? 'Uploading...'
                                        : 'Upload Verification Document'
                                }}</span>
                            </Button>
                        </form>

                        <div
                            v-if="profile && profile.documents.length > 0"
                            class="space-y-2 border-t border-border pt-3"
                        >
                            <p
                                class="text-xs font-semibold text-muted-foreground uppercase"
                            >
                                Uploaded Files ({{ profile.documents.length }})
                            </p>
                            <ul class="space-y-2">
                                <li
                                    v-for="doc in profile.documents"
                                    :key="doc.id"
                                    class="flex items-start justify-between gap-2 rounded-lg border border-border p-2.5 text-xs"
                                >
                                    <div class="min-w-0 space-y-0.5">
                                        <p
                                            class="truncate font-medium text-foreground"
                                        >
                                            {{ doc.original_filename }}
                                        </p>
                                        <p class="text-muted-foreground">
                                            {{ doc.document_type_label }} ·
                                            {{ formatBytes(doc.size_bytes) }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-1"
                                    >
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="icon"
                                            class="size-8"
                                        >
                                            <a
                                                :href="
                                                    downloadDocument.url(doc.id)
                                                "
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                :aria-label="`Download ${doc.original_filename}`"
                                            >
                                                <Download
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="canEdit"
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-8 text-destructive hover:text-destructive"
                                            :aria-label="`Delete ${doc.original_filename}`"
                                            @click="removeDocument(doc.id)"
                                        >
                                            <Trash2
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="profile && profile.status_history.length > 0">
                    <CardHeader>
                        <CardTitle class="text-base">Review Timeline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol class="space-y-3 text-xs">
                            <li
                                v-for="entry in profile.status_history"
                                :key="entry.id"
                                class="border-l-2 border-primary/40 pl-3"
                            >
                                <p class="font-semibold text-foreground">
                                    {{ entry.to_status }}
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
        </div>
    </div>
</template>
