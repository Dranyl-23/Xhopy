<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock3,
    Landmark,
    ReceiptText,
    ShieldCheck,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
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
import { dashboard as sellerDashboard } from '@/routes/seller';
import { index as sellerFinanceIndex } from '@/routes/seller/finance';
import { store as sellerPayoutStore } from '@/routes/seller/finance/payouts';
import type { SellerFinancePageProps } from '@modules/seller/types';

const props = defineProps<SellerFinancePageProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Seller Portal', href: sellerDashboard() },
            { title: 'Finance & Payouts', href: sellerFinanceIndex() },
        ],
    },
});

const payoutMethodOptions = [
    { value: 'bank_transfer', label: 'Bank Transfer (PESONet / InstaPay)' },
    { value: 'gcash', label: 'GCash Wallet' },
    { value: 'maya', label: 'Maya Wallet' },
] as const;

const canRequestPayout = computed(
    () =>
        !props.store.is_official &&
        props.balances.availableCentavos >=
            props.balances.minimumPayoutCentavos,
);

const form = useForm<{
    amount: string;
    payout_method: string;
    payout_destination: string;
    request_notes: string;
}>({
    amount:
        props.balances.availableCentavos >= props.balances.minimumPayoutCentavos
            ? (props.balances.availableCentavos / 100).toFixed(2)
            : '',
    payout_method: 'bank_transfer',
    payout_destination: props.store.payout_notes_default || '',
    request_notes: '',
});

const generalError = computed<string | null>(() => {
    const first = Object.values(form.errors).find(
        (msg): msg is string => typeof msg === 'string' && msg !== '',
    );

    return first ?? null;
});

function submitPayoutRequest(): void {
    form.post(sellerPayoutStore.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.amount =
                props.balances.availableCentavos >=
                props.balances.minimumPayoutCentavos
                    ? (props.balances.availableCentavos / 100).toFixed(2)
                    : '';
            form.request_notes = '';
        },
    });
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
    <Head :title="`Finance & Payouts — ${store.name}`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-border bg-card p-6 sm:flex-row sm:items-center"
        >
            <div class="space-y-1">
                <Link
                    :href="sellerDashboard()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-primary"
                >
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to Seller Dashboard</span>
                </Link>
                <h1 class="text-2xl font-bold tracking-tight">
                    Finance, Commission &amp; Payouts
                </h1>
                <p class="text-sm text-muted-foreground">
                    Server-verified earnings ledger for
                    <strong class="text-foreground">{{ store.name }}</strong> ·
                    Active rate:
                    <strong class="text-foreground">{{
                        balances.commissionRatePercentFormatted
                    }}</strong>
                    · Hold window:
                    <strong class="text-foreground"
                        >{{ balances.holdDays }} days</strong
                    >
                    after delivery.
                </p>
            </div>
        </div>

        <Alert v-if="generalError" variant="destructive">
            <AlertTriangle class="size-4" aria-hidden="true" />
            <AlertTitle>Withdrawal Validation</AlertTitle>
            <AlertDescription>{{ generalError }}</AlertDescription>
        </Alert>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Available to Withdraw</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl font-extrabold text-primary tabular-nums"
                    >
                        <Wallet class="size-5" aria-hidden="true" />
                        <span>{{ balances.availableFormatted }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Minimum withdrawal: {{ balances.minimumPayoutFormatted }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription
                        >On Hold ({{ balances.holdDays }}-Day
                        Window)</CardDescription
                    >
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <Clock3
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ balances.onHoldFormatted }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Delivered net earnings maturing before release.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>In Fulfillment</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <ReceiptText
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ balances.inFulfillmentFormatted }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Net earnings on Paid, Processing, &amp; Shipped suborders.
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total Paid Out</CardDescription>
                    <CardTitle
                        class="flex items-center gap-2 text-2xl tabular-nums"
                    >
                        <CheckCircle2
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ balances.paidOutFormatted }}</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Pending payout review:
                    {{ balances.pendingPayoutFormatted }}
                </CardContent>
            </Card>
        </div>

        <Alert>
            <ShieldCheck class="size-4" aria-hidden="true" />
            <AlertTitle>Commission &amp; Ledger Summary</AlertTitle>
            <AlertDescription>
                Confirmed gross suborder sales:
                <strong>{{ balances.grossConfirmedFormatted }}</strong> · Total
                platform commission deducted:
                <strong>{{ balances.commissionChargedFormatted }}</strong> ·
                Matured lifetime net:
                <strong>{{ balances.maturedFormatted }}</strong
                >. Commission rates are snapshotted per suborder upon payment
                confirmation.
            </AlertDescription>
        </Alert>

        <div class="grid gap-6 lg:grid-cols-12">
            <div class="min-w-0 space-y-6 lg:col-span-5">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Landmark
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span>Request Withdrawal</span>
                        </CardTitle>
                        <CardDescription>
                            All payout requests undergo Super Admin review and
                            manual disbursement confirmation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            class="space-y-4"
                            @submit.prevent="submitPayoutRequest"
                        >
                            <div class="space-y-1.5">
                                <Label for="payout-amount">Amount (PHP)</Label>
                                <Input
                                    id="payout-amount"
                                    v-model="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    :placeholder="`Min ${balances.minimumPayoutFormatted}`"
                                    class="h-11"
                                />
                                <InputError :message="form.errors.amount" />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="payout-method"
                                    >Payout Channel</Label
                                >
                                <SelectField
                                    id="payout-method"
                                    v-model="form.payout_method"
                                    :options="[...payoutMethodOptions]"
                                    aria-label="Select payout channel"
                                />
                                <InputError
                                    :message="form.errors.payout_method"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="payout-destination">
                                    Account / Wallet Details
                                </Label>
                                <Input
                                    id="payout-destination"
                                    v-model="form.payout_destination"
                                    placeholder="e.g., BPI Savings **** 4821 — Juan Dela Cruz"
                                    class="h-11"
                                />
                                <InputError
                                    :message="form.errors.payout_destination"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label for="payout-notes"
                                    >Notes (Optional)</Label
                                >
                                <Input
                                    id="payout-notes"
                                    v-model="form.request_notes"
                                    placeholder="Optional settlement reference"
                                    class="h-11"
                                />
                            </div>

                            <Button
                                type="submit"
                                class="h-11 w-full"
                                :disabled="form.processing || !canRequestPayout"
                            >
                                Submit Payout Request
                            </Button>

                            <p
                                v-if="!canRequestPayout"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    store.is_official
                                        ? 'Official flagship balances settle internally.'
                                        : `Withdrawal unlocks once Available Balance reaches ${balances.minimumPayoutFormatted}.`
                                }}
                            </p>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Payout Request History
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EmptyState
                            v-if="payouts.length === 0"
                            :icon="Landmark"
                            title="No payout requests yet"
                            description="Submitted withdrawal requests and reference receipts will appear here."
                        />
                        <ul v-else class="divide-y divide-border text-sm">
                            <li
                                v-for="payout in payouts"
                                :key="payout.id"
                                class="space-y-1 py-3 first:pt-0 last:pb-0"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <span
                                        class="font-mono text-xs font-bold text-foreground"
                                    >
                                        {{ payout.payout_number }}
                                    </span>
                                    <Badge
                                        :variant="
                                            payoutBadgeVariant(payout.status)
                                        "
                                    >
                                        {{ payout.status_label }}
                                    </Badge>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="text-xs text-muted-foreground uppercase"
                                    >
                                        {{
                                            payout.payout_method.replace(
                                                '_',
                                                ' ',
                                            )
                                        }}
                                        · {{ payout.payout_destination }}
                                    </span>
                                    <strong class="tabular-nums">{{
                                        payout.amount_formatted
                                    }}</strong>
                                </div>
                                <p
                                    v-if="payout.external_reference"
                                    class="text-xs text-muted-foreground"
                                >
                                    Transfer Ref:
                                    <strong class="text-foreground">{{
                                        payout.external_reference
                                    }}</strong>
                                </p>
                                <p
                                    v-if="payout.review_notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    Admin Note: {{ payout.review_notes }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Requested {{ payout.requested_at_diff }}
                                    <span v-if="payout.paid_at_diff">
                                        · Paid {{ payout.paid_at_diff }}
                                    </span>
                                </p>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <div class="min-w-0 space-y-6 lg:col-span-7">
                <Card class="overflow-hidden">
                    <CardHeader>
                        <CardTitle class="text-base">
                            Suborder Commission Snapshots
                        </CardTitle>
                        <CardDescription>
                            Locked fee calculation captured per suborder on
                            payment settlement.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="commissionSnapshots.length === 0"
                            class="p-6"
                        >
                            <EmptyState
                                :icon="ReceiptText"
                                title="No settled suborders yet"
                                description="Paid store suborders will display gross, commission, and net earnings here."
                            />
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="border-b border-border bg-muted/50 text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-2.5">Suborder</th>
                                        <th class="px-4 py-2.5">Gross</th>
                                        <th class="px-4 py-2.5">Fee</th>
                                        <th class="px-4 py-2.5">Net Seller</th>
                                        <th class="px-4 py-2.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="snap in commissionSnapshots"
                                        :key="snap.id"
                                    >
                                        <td class="px-4 py-3">
                                            <p
                                                class="font-mono font-semibold text-foreground"
                                            >
                                                {{ snap.seller_order_number }}
                                            </p>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                {{ snap.suborder_status_label }}
                                                <span
                                                    v-if="snap.hold_until_label"
                                                >
                                                    · Hold ends
                                                    {{ snap.hold_until_label }}
                                                </span>
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 tabular-nums">
                                            {{ snap.gross_formatted }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground tabular-nums"
                                        >
                                            {{ snap.commission_formatted }} ({{
                                                snap.rate_percent
                                            }})
                                        </td>
                                        <td
                                            class="px-4 py-3 font-bold text-foreground tabular-nums"
                                        >
                                            {{ snap.net_formatted }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    snap.status === 'reversed'
                                                        ? 'destructive'
                                                        : 'outline'
                                                "
                                                class="uppercase"
                                            >
                                                {{ snap.status }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Immutable Financial Ledger
                        </CardTitle>
                        <CardDescription>
                            Append-only credit, reversal, and withdrawal audit
                            entries.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EmptyState
                            v-if="ledgerEntries.length === 0"
                            :icon="Wallet"
                            title="Ledger is empty"
                            description="Settled order credits and payout withdrawals appear here automatically."
                        />
                        <ul v-else class="divide-y divide-border text-sm">
                            <li
                                v-for="entry in ledgerEntries"
                                :key="entry.id"
                                class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <div class="min-w-0 space-y-0.5">
                                    <p class="font-semibold text-foreground">
                                        {{ entry.entry_type_label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ entry.description }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ entry.created_at_diff }}
                                    </p>
                                </div>
                                <span
                                    :class="[
                                        'shrink-0 font-bold tabular-nums',
                                        entry.amount_centavos >= 0
                                            ? 'text-primary'
                                            : 'text-destructive',
                                    ]"
                                >
                                    {{ entry.amount_formatted }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
