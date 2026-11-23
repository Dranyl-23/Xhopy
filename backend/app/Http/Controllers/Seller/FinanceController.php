<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Finance\Actions\RequestSellerPayoutAction;
use App\Domain\Finance\Models\CommissionEntry;
use App\Domain\Finance\Models\SellerLedgerEntry;
use App\Domain\Finance\Models\SellerPayout;
use App\Domain\Finance\Services\SellerFinanceService;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Services\SellerWorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\RequestSellerPayoutRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __construct(
        private readonly SellerWorkspaceService $workspaceService,
        private readonly SellerFinanceService $financeService,
    ) {}

    public function index(Request $request): Response
    {
        ['profile' => $profile, 'store' => $store] = $this->workspaceService->requireApprovedWorkspace($request->user());

        $balances = $this->financeService->balancesForStore($store);

        $payouts = SellerPayout::query()
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(static fn (SellerPayout $payout): array => [
                'id' => $payout->id,
                'payout_number' => $payout->payout_number,
                'status' => $payout->status->value,
                'status_label' => $payout->status->label(),
                'amount_formatted' => SellerFinanceService::formatCentavos($payout->amount_centavos, $payout->currency),
                'payout_method' => $payout->payout_method,
                'payout_destination' => $payout->payout_destination,
                'external_reference' => $payout->external_reference,
                'review_notes' => $payout->review_notes,
                'requested_at_diff' => $payout->requested_at->diffForHumans(),
                'paid_at_diff' => $payout->paid_at?->diffForHumans(),
            ])
            ->values()
            ->all();

        $ledgerEntries = SellerLedgerEntry::query()
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(static fn (SellerLedgerEntry $entry): array => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type->value,
                'entry_type_label' => $entry->entry_type->label(),
                'amount_centavos' => $entry->amount_centavos,
                'amount_formatted' => ($entry->amount_centavos > 0 ? '+' : '').SellerFinanceService::formatCentavos($entry->amount_centavos, $entry->currency),
                'description' => $entry->description,
                'created_at_diff' => $entry->created_at->diffForHumans(),
            ])
            ->values()
            ->all();

        $commissionSnapshots = CommissionEntry::query()
            ->with(['sellerOrder:id,seller_order_number,status,hold_until,delivered_at'])
            ->where('store_id', $store->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(static fn (CommissionEntry $entry): array => [
                'id' => $entry->id,
                'seller_order_id' => $entry->seller_order_id,
                'seller_order_number' => $entry->sellerOrder->seller_order_number,
                'suborder_status_label' => $entry->sellerOrder->status->label(),
                'gross_formatted' => SellerFinanceService::formatCentavos($entry->gross_amount_centavos),
                'rate_percent' => number_format($entry->rate_bps / 100, 2).'%',
                'commission_formatted' => SellerFinanceService::formatCentavos($entry->commission_centavos),
                'net_formatted' => SellerFinanceService::formatCentavos($entry->net_seller_centavos),
                'status' => $entry->status,
                'hold_until_label' => $entry->sellerOrder->hold_until?->toDayDateTimeString(),
                'recorded_at_diff' => $entry->recorded_at->diffForHumans(),
            ])
            ->values()
            ->all();

        return Inertia::render('seller/Finance', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'is_official' => $store->is_official,
                'payout_notes_default' => $profile->payout_notes ?? '',
            ],
            'balances' => $balances,
            'payouts' => $payouts,
            'ledgerEntries' => $ledgerEntries,
            'commissionSnapshots' => $commissionSnapshots,
        ]);
    }

    public function storePayout(
        RequestSellerPayoutRequest $request,
        RequestSellerPayoutAction $requestPayout,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        ['store' => $store] = $this->workspaceService->requireApprovedWorkspace($request->user());
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $amountCentavos = MoneyFormatter::toCentavos((string) $request->validated('amount'));

        $payout = $requestPayout->execute(
            store: $store,
            actor: $actor,
            amountCentavos: $amountCentavos,
            payoutMethod: (string) $request->validated('payout_method'),
            payoutDestination: (string) $request->validated('payout_destination'),
            requestNotes: is_string($request->validated('request_notes'))
                ? (string) $request->validated('request_notes')
                : null,
        );

        $auditLogger->record(
            event: SecurityAuditEvent::SellerPayoutRequested,
            user: $actor,
            actor: $actor,
            metadata: [
                'store_id' => $store->id,
                'payout_id' => $payout->id,
                'payout_number' => $payout->payout_number,
                'amount_centavos' => $payout->amount_centavos,
                'payout_method' => $payout->payout_method,
            ],
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("Payout request {$payout->payout_number} submitted for Super Admin review."),
        ]);

        return back();
    }
}
