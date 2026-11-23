<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Finance\Actions\ReviewSellerPayoutAction;
use App\Domain\Finance\Actions\UpdateCommissionRuleAction;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Models\CommissionEntry;
use App\Domain\Finance\Models\CommissionRule;
use App\Domain\Finance\Models\SellerPayout;
use App\Domain\Finance\Services\SellerFinanceService;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\Store;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewSellerPayoutRequest;
use App\Http\Requests\Admin\StoreCommissionRuleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:24'],
        ]);
        $statusFilter = isset($validated['status']) ? PayoutStatus::tryFrom((string) $validated['status']) : null;

        $recordedTotals = CommissionEntry::query()
            ->where('status', 'recorded')
            ->select([
                DB::raw('SUM(gross_amount_centavos) as gmv_sum'),
                DB::raw('SUM(commission_centavos) as commission_sum'),
                DB::raw('SUM(net_seller_centavos) as net_sum'),
            ])
            ->first();

        $reversedCentavos = (int) (CommissionEntry::query()
            ->where('status', 'reversed')
            ->sum('net_seller_centavos') ?: 0);

        $pendingPayoutCentavos = (int) (SellerPayout::query()
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Approved->value])
            ->sum('amount_centavos') ?: 0);

        $pendingPayoutCount = SellerPayout::query()
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Approved->value])
            ->count();

        $paidOutCentavos = (int) (SellerPayout::query()
            ->where('status', PayoutStatus::Paid->value)
            ->sum('amount_centavos') ?: 0);

        $gmvCentavos = (int) ($recordedTotals?->getAttribute('gmv_sum') ?? 0);
        $commissionRevenueCentavos = (int) ($recordedTotals?->getAttribute('commission_sum') ?? 0);
        $sellerNetPayableCentavos = (int) ($recordedTotals?->getAttribute('net_sum') ?? 0);

        $rules = CommissionRule::query()
            ->with(['store:id,name,slug', 'creator:id,name'])
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(static fn (CommissionRule $rule): array => [
                'id' => $rule->id,
                'store_id' => $rule->store_id,
                'scope_label' => $rule->store !== null ? $rule->store->name : 'Global Default (All Seller Stores)',
                'rate_percent' => number_format($rule->rate_bps / 100, 2),
                'rate_bps' => $rule->rate_bps,
                'hold_days' => $rule->hold_days,
                'minimum_payout_formatted' => SellerFinanceService::formatCentavos($rule->minimum_payout_centavos),
                'minimum_payout_amount' => number_format($rule->minimum_payout_centavos / 100, 2, '.', ''),
                'is_active' => $rule->is_active,
                'effective_from_diff' => $rule->effective_from->diffForHumans(),
                'creator_name' => $rule->creator !== null ? $rule->creator->name : 'System Default',
                'notes' => $rule->notes,
            ])
            ->values()
            ->all();

        $payoutQuery = SellerPayout::query()
            ->with(['store:id,name,slug', 'requester:id,name,email', 'reviewer:id,name'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('id');

        if ($statusFilter instanceof PayoutStatus) {
            $payoutQuery->where('status', $statusFilter->value);
        }

        $payouts = $payoutQuery->paginate(20)->withQueryString()->through(static function (SellerPayout $payout): array {
            $store = $payout->store;
            $requester = $payout->requester;

            return [
                'id' => $payout->id,
                'payout_number' => $payout->payout_number,
                'store_name' => $store->name,
                'requester_name' => $requester->name,
                'requester_email' => $requester->email,
                'status' => $payout->status->value,
                'status_label' => $payout->status->label(),
                'amount_formatted' => SellerFinanceService::formatCentavos($payout->amount_centavos, $payout->currency),
                'payout_method' => $payout->payout_method,
                'payout_destination' => $payout->payout_destination,
                'request_notes' => $payout->request_notes,
                'review_notes' => $payout->review_notes,
                'external_reference' => $payout->external_reference,
                'reviewer_name' => $payout->reviewer?->name,
                'requested_at_diff' => $payout->requested_at->diffForHumans(),
                'paid_at_diff' => $payout->paid_at?->diffForHumans(),
                'allowed_transitions' => array_map(
                    static fn (PayoutStatus $status): array => [
                        'value' => $status->value,
                        'label' => $status->label(),
                    ],
                    $payout->status->allowedAdminTransitions(),
                ),
            ];
        });

        $stores = Store::query()
            ->where('status', StoreStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'is_official'])
            ->map(static fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name.($store->is_official ? ' (Official)' : ''),
            ])
            ->values()
            ->all();

        $payoutStatuses = array_map(
            static fn (PayoutStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            PayoutStatus::cases(),
        );

        return Inertia::render('admin/Finance', [
            'kpis' => [
                'gmvFormatted' => SellerFinanceService::formatCentavos($gmvCentavos),
                'commissionRevenueFormatted' => SellerFinanceService::formatCentavos($commissionRevenueCentavos),
                'sellerNetPayableFormatted' => SellerFinanceService::formatCentavos($sellerNetPayableCentavos),
                'reversedFormatted' => SellerFinanceService::formatCentavos($reversedCentavos),
                'pendingPayoutFormatted' => SellerFinanceService::formatCentavos($pendingPayoutCentavos),
                'pendingPayoutCount' => $pendingPayoutCount,
                'paidOutFormatted' => SellerFinanceService::formatCentavos($paidOutCentavos),
            ],
            'rules' => $rules,
            'stores' => $stores,
            'payouts' => $payouts,
            'payoutStatuses' => $payoutStatuses,
            'filters' => [
                'status' => $statusFilter instanceof PayoutStatus ? $statusFilter->value : '',
            ],
        ]);
    }

    public function storeCommissionRule(
        StoreCommissionRuleRequest $request,
        UpdateCommissionRuleAction $updateRule,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $storeId = $request->validated('store_id');
        $store = $storeId !== null ? Store::query()->findOrFail((int) $storeId) : null;
        $rateBps = (int) round(((float) $request->validated('rate_percent')) * 100);
        $holdDays = (int) $request->validated('hold_days');
        $minPayoutCentavos = MoneyFormatter::toCentavos((string) $request->validated('minimum_payout_amount'));

        $rule = $updateRule->execute(
            store: $store,
            rateBps: $rateBps,
            holdDays: $holdDays,
            minimumPayoutCentavos: $minPayoutCentavos,
            actor: $actor,
            notes: is_string($request->validated('notes')) ? (string) $request->validated('notes') : null,
        );

        $auditLogger->record(
            event: SecurityAuditEvent::AdminCommissionRuleUpdated,
            user: null,
            actor: $actor,
            metadata: [
                'commission_rule_id' => $rule->id,
                'store_id' => $rule->store_id,
                'rate_bps' => $rule->rate_bps,
                'hold_days' => $rule->hold_days,
                'minimum_payout_centavos' => $rule->minimum_payout_centavos,
            ],
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Updated active commission rule ('.number_format($rule->rate_bps / 100, 2).'%). Historical orders retain prior snapshots.'),
        ]);

        return back();
    }

    public function updatePayout(
        ReviewSellerPayoutRequest $request,
        SellerPayout $payout,
        ReviewSellerPayoutAction $reviewPayout,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $nextStatus = PayoutStatus::from((string) $request->validated('status'));

        $updated = $reviewPayout->execute(
            payout: $payout,
            nextStatus: $nextStatus,
            reviewer: $actor,
            reviewNotes: is_string($request->validated('review_notes')) ? (string) $request->validated('review_notes') : null,
            externalReference: is_string($request->validated('external_reference')) ? (string) $request->validated('external_reference') : null,
        );

        $updated->loadMissing('requester');

        $auditLogger->record(
            event: SecurityAuditEvent::AdminPayoutUpdated,
            user: $updated->requester,
            actor: $actor,
            metadata: [
                'payout_id' => $updated->id,
                'payout_number' => $updated->payout_number,
                'store_id' => $updated->store_id,
                'to_status' => $updated->status->value,
                'external_reference' => $updated->external_reference,
            ],
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("Payout {$updated->payout_number} updated to {$updated->status->label()}."),
        ]);

        return back();
    }
}
