<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Landmark,
    Percent,
    ReceiptText,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import EmptyState from '@shared/components/EmptyState.vue';
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
import { index as adminFinanceIndex } from '@/routes/admin/finance';
import { store as adminCommissionRulesStore } from '@/routes/admin/finance/commission-rules';
import { update as adminPayoutUpdate } from '@/routes/admin/finance/payouts';
import type { AdminFinancePageProps } from '@modules/admin/types';

const props = defineProps<AdminFinancePageProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Finance & Payouts', href: adminFinanceIndex() },
        ],
    },
});

const storeOptions = computed(() =>
    props.stores.map((store) => ({
        value: store.id,
        label: store.name,
    })),
);

const activeGlobalRule = computed(
    () => props.rules.find((r) => r.is_active && r.store_id === null) ?? null,
);

const ruleForm = useForm<{
    store_id: number | '';
    rate_percent: string;
    hold_days: string;
    minimum_payout_amount: string;
    notes: string;
}>({
    store_id: '',
    rate_percent: activeGlobalRule.value?.rate_percent ?? '5.00',
    hold_days: String(activeGlobalRule.value?.hold_days ?? 7),
    minimum_payout_amount:
        activeGlobalRule.value?.minimum_payout_amount ?? '500.00',
    notes: '',
});

const generalRuleError = computed<string | null>(() => {
    const first = Object.values(ruleForm.errors).find(
        (msg): msg is string => typeof msg === 'string' && msg !== '',
    );

    return first ?? null;
});

function submitCommissionRule(): void {
    ruleForm
        .transform((data) => ({
            ...data,
            store_id: data.store_id === '' ? null : Number(data.store_id),
        }))
        .post(adminCommissionRulesStore.url(), {
            preserveScroll: true,
            onSuccess: () => {
                ruleForm.notes = '';
            },
        });
}

interface PayoutReviewDraft {
    status: string;
    external_reference: string;
    review_notes: string;
    processing: boolean;
}

const payoutDrafts = reactive<Record<number, PayoutReviewDraft>>({});

function syncPayoutDrafts(): void {
    for (const payout of props.payouts.data) {
        const firstStatus = payout.allowed_transitions[0]?.value ?? '';
        const existing = payoutDrafts[payout.id];
        if (!existing) {
            payoutDrafts[payout.id] = {
                status: firstStatus,
                external_reference: payout.external_reference ?? '',
                review_notes: payout.review_notes ?? '',
                processing: false,
            };
        } else {
            existing.status = firstStatus;
            existing.external_reference = payout.external_reference ?? '';
            existing.review_notes = payout.review_notes ?? '';
        }
    }
}

syncPayoutDrafts();
watch(() => props.payouts.data, syncPayoutDrafts, { deep: true });

function submitPayoutReview(payoutId: number): void {
    const draft = payoutDrafts[payoutId];
    if (!draft || !draft.status || draft.processing) {
        return;
    }

    draft.processing = true;
    router.patch(
        adminPayoutUpdate.url(payoutId),
        {
            status: draft.status,
            external_reference: draft.external_reference || undefined,
            review_notes: draft.review_notes || undefined,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                draft.processing = false;
            },
        },
    );
}

function payoutBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'paid') {
        return 'default';
    }
    if (status === 'rejected') {
        return 'destructive';
    }

    return 'secondary';
}
</script>

<template>
    <Head title="Finance, Commissions & Payouts — Super Admin" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div class="space-y-1">
            <h1
                class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
            >
                <Wallet class="size-6 text-primary" aria-hidden="true" />
                <span>Marketplace Finance, Commissions &amp; Payouts</span>
            </h1>
            <p class="text-sm text-muted-foreground">
                Super Admin exclusive financial governance separating GMV,
                platform commission revenue, seller net payable, holds, and
                payout disbursements.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Recorded Suborder GMV</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ kpis.gmvFormatted }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Active non-reversed settled GMV.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Platform Commission</CardDescription>
                    <CardTitle
                        class="text-2xl font-extrabold text-primary tabular-nums"
                    >
                        {{ kpis.commissionRevenueFormatted }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Snapshotted platform fee revenue.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Seller Net Payable</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ kpis.sellerNetPayableFormatted }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Reversed post-payment: {{ kpis.reversedFormatted }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Pending Payout Queue</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ kpis.pendingPayoutFormatted }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    {{ kpis.pendingPayoutCount }} open withdrawal request(s).
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total Disbursed</CardDescription>
                    <CardTitle class="text-2xl font-extrabold tabular-nums">
                        {{ kpis.paidOutFormatted }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Confirmed manual seller payouts.
                </CardContent>
            </Card>
        </div>

        <Alert v-if="generalRuleError" variant="destructive">
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>Commission Rule Error</AlertTitle>
            <AlertDescription>{{ generalRuleError }}</AlertDescription>
        </Alert>

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Left: Commission Rules -->
            <div class="min-w-0 space-y-6 lg:col-span-5">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Percent
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Update Commission &amp; Hold Rule</span>
                        </CardTitle>
                        <CardDescription>
                            Creates an effective-dated rule version cleanly.
                            Existing settled suborders keep their snapshotted
                            rates.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            class="space-y-4"
                            @submit.prevent="submitCommissionRule"
                        >
                            <div class="space-y-1.5">
                                <Label for="rule-store-scope">Rule Scope</Label>
                                <SelectField
                                    id="rule-store-scope"
                                    v-model="ruleForm.store_id"
                                    :options="storeOptions"
                                    empty-option-label="Global Default (All Seller Stores)"
                                    aria-label="Select rule store scope"
                                />
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="space-y-1.5">
                                    <Label for="rule-rate">Fee (%)</Label>
                                    <Input
                                        id="rule-rate"
                                        v-model="ruleForm.rate_percent"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="50"
                                        class="h-10"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="rule-hold-days"
                                        >Hold (Days)</Label
                                    >
                                    <Input
                                        id="rule-hold-days"
                                        v-model="ruleForm.hold_days"
                                        type="number"
                                        min="0"
                                        max="90"
                                        class="h-10"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="rule-min-payout">Min (₱)</Label>
                                    <Input
                                        id="rule-min-payout"
                                        v-model="ruleForm.minimum_payout_amount"
                                        type="number"
                                        step="1"
                                        min="1"
                                        class="h-10"
                                    />
                                </div>
                            </div>
                            <InputError
                                :message="
                                    ruleForm.errors.rate_percent ??
                                    ruleForm.errors.hold_days ??
                                    ruleForm.errors.minimum_payout_amount
                                "
                            />

                            <div class="space-y-1.5">
                                <Label for="rule-notes">Policy Note</Label>
                                <Input
                                    id="rule-notes"
                                    v-model="ruleForm.notes"
                                    placeholder="e.g., Partner promotion or standard fee change"
                                    class="h-10"
                                />
                            </div>

                            <Button
                                type="submit"
                                class="h-10 w-full"
                                :disabled="ruleForm.processing"
                            >
                                Save Active Rule Version
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <ShieldCheck
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Commission Rule History</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="divide-y divide-border text-sm">
                            <li
                                v-for="rule in rules"
                                :key="rule.id"
                                class="space-y-1 py-3 first:pt-0 last:pb-0"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span class="font-semibold text-foreground">
                                        {{ rule.scope_label }}
                                    </span>
                                    <Badge
                                        :variant="
                                            rule.is_active
                                                ? 'default'
                                                : 'outline'
                                        "
                                    >
                                        {{
                                            rule.is_active
                                                ? 'Active'
                                                : 'Superseded'
                                        }}
                                    </Badge>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Rate:
                                    <strong class="text-foreground"
                                        >{{ rule.rate_percent }}%</strong
                                    >
                                    · Hold:
                                    <strong class="text-foreground"
                                        >{{ rule.hold_days }}d</strong
                                    >
                                    · Min:
                                    <strong class="text-foreground">{{
                                        rule.minimum_payout_formatted
                                    }}</strong>
                                </p>
                                <p
                                    v-if="rule.notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ rule.notes }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Effective {{ rule.effective_from_diff }} by
                                    {{ rule.creator_name }}
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Right: Seller Payout Review Queue -->
            <div class="min-w-0 space-y-6 lg:col-span-7">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Landmark
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Seller Payout Queue &amp; Disbursement</span>
                        </CardTitle>
                        <CardDescription>
                            Approve pending withdrawals, record bank/e-wallet
                            transfer references on disbursement, or reject to
                            return funds to seller balance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <EmptyState
                            v-if="payouts.data.length === 0"
                            :icon="ReceiptText"
                            title="No payout requests"
                            description="Seller withdrawal requests will queue here for Super Admin review."
                        />
                        <div
                            v-for="payout in payouts.data"
                            v-else
                            :key="payout.id"
                            class="space-y-3 rounded-xl border border-border bg-secondary/20 p-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="space-y-0.5">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="font-mono text-xs font-bold"
                                        >
                                            {{ payout.payout_number }}
                                        </span>
                                        <Badge
                                            :variant="
                                                payoutBadgeVariant(
                                                    payout.status,
                                                )
                                            "
                                        >
                                            {{ payout.status_label }}
                                        </Badge>
                                    </div>
                                    <p class="text-sm font-semibold">
                                        {{ payout.store_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Requested by
                                        {{ payout.requester_name }} ({{
                                            payout.requester_email
                                        }}) ·
                                        {{ payout.requested_at_diff }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p
                                        class="text-lg font-extrabold text-primary tabular-nums"
                                    >
                                        {{ payout.amount_formatted }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground uppercase"
                                    >
                                        {{
                                            payout.payout_method.replace(
                                                '_',
                                                ' ',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                class="rounded-lg border border-border bg-card px-3 py-2 text-xs text-foreground"
                            >
                                Destination:
                                <strong>{{ payout.payout_destination }}</strong>
                            </p>

                            <p
                                v-if="payout.request_notes"
                                class="text-xs text-muted-foreground"
                            >
                                Seller note: {{ payout.request_notes }}
                            </p>
                            <p
                                v-if="payout.external_reference"
                                class="text-xs text-muted-foreground"
                            >
                                External Transfer Ref:
                                <strong class="text-foreground">{{
                                    payout.external_reference
                                }}</strong>
                            </p>
                            <p
                                v-if="payout.review_notes"
                                class="text-xs text-muted-foreground"
                            >
                                Review Note:
                                <strong class="text-foreground">{{
                                    payout.review_notes
                                }}</strong>
                            </p>

                            <form
                                v-if="
                                    payout.allowed_transitions.length > 0 &&
                                    payoutDrafts[payout.id]
                                "
                                class="grid gap-3 border-t border-border/70 pt-3 sm:grid-cols-4 sm:items-end"
                                @submit.prevent="submitPayoutReview(payout.id)"
                            >
                                <div class="space-y-1">
                                    <Label
                                        :for="`payout-${payout.id}-status`"
                                        class="text-xs"
                                    >
                                        Decision
                                    </Label>
                                    <SelectField
                                        :id="`payout-${payout.id}-status`"
                                        v-model="payoutDrafts[payout.id].status"
                                        :options="payout.allowed_transitions"
                                        aria-label="Select payout action"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label
                                        :for="`payout-${payout.id}-ref`"
                                        class="text-xs"
                                    >
                                        Transfer Ref
                                    </Label>
                                    <Input
                                        :id="`payout-${payout.id}-ref`"
                                        v-model="
                                            payoutDrafts[payout.id]
                                                .external_reference
                                        "
                                        placeholder="Bank / GCash Ref"
                                        class="h-10"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label
                                        :for="`payout-${payout.id}-note`"
                                        class="text-xs"
                                    >
                                        Review Note
                                    </Label>
                                    <Input
                                        :id="`payout-${payout.id}-note`"
                                        v-model="
                                            payoutDrafts[payout.id].review_notes
                                        "
                                        placeholder="Approval / reason"
                                        class="h-10"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="h-10"
                                    :disabled="
                                        payoutDrafts[payout.id].processing ||
                                        !payoutDrafts[payout.id].status
                                    "
                                >
                                    Apply Payout
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
