<?php

namespace App\Domain\Finance\Services;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Finance\Enums\LedgerEntryType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Models\CommissionEntry;
use App\Domain\Finance\Models\CommissionRule;
use App\Domain\Finance\Models\SellerLedgerEntry;
use App\Domain\Finance\Models\SellerPayout;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class SellerFinanceService
{
    public const int DEFAULT_SELLER_RATE_BPS = 500;

    public const int DEFAULT_HOLD_DAYS = 7;

    public const int DEFAULT_MINIMUM_PAYOUT_CENTAVOS = 50000;

    /**
     * Resolve effective commission rule configuration for a store cleanly.
     *
     * @return array{rule: CommissionRule|null, rate_bps: int, hold_days: int, minimum_payout_centavos: int}
     */
    public function activeRuleForStore(Store $store): array
    {
        $now = Date::now();

        $storeOverride = CommissionRule::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->orderByDesc('id')
            ->first();

        if ($storeOverride instanceof CommissionRule) {
            return [
                'rule' => $storeOverride,
                'rate_bps' => $storeOverride->rate_bps,
                'hold_days' => $storeOverride->hold_days,
                'minimum_payout_centavos' => $storeOverride->minimum_payout_centavos,
            ];
        }

        $globalRule = CommissionRule::query()
            ->whereNull('store_id')
            ->where('is_active', true)
            ->where('effective_from', '<=', $now)
            ->orderByDesc('id')
            ->first();

        if ($globalRule instanceof CommissionRule) {
            return [
                'rule' => $globalRule,
                'rate_bps' => $store->is_official ? 0 : $globalRule->rate_bps,
                'hold_days' => $globalRule->hold_days,
                'minimum_payout_centavos' => $globalRule->minimum_payout_centavos,
            ];
        }

        return [
            'rule' => null,
            'rate_bps' => $store->is_official ? 0 : self::DEFAULT_SELLER_RATE_BPS,
            'hold_days' => self::DEFAULT_HOLD_DAYS,
            'minimum_payout_centavos' => self::DEFAULT_MINIMUM_PAYOUT_CENTAVOS,
        ];
    }

    /**
     * Snapshot commission and append initial immutable earning ledger entry once a suborder settles to Paid.
     */
    public function recordSuborderSettled(SellerOrder $sellerOrder, ?User $actor = null): void
    {
        $sellerOrder->loadMissing('store');
        $store = $sellerOrder->store;

        if (CommissionEntry::query()->where('seller_order_id', $sellerOrder->id)->exists()) {
            return;
        }

        $now = Date::now();
        $ruleData = $this->activeRuleForStore($store);
        $grossCentavos = MoneyFormatter::toCentavos($sellerOrder->total_amount);
        $commissionCentavos = (int) round(($grossCentavos * $ruleData['rate_bps']) / 10000);
        $netCentavos = $grossCentavos - $commissionCentavos;

        $sellerOrder->forceFill([
            'commission_rule_id' => $ruleData['rule']?->id,
            'commission_rate_bps' => $ruleData['rate_bps'],
            'commission_amount' => number_format($commissionCentavos / 100, 2, '.', ''),
            'net_seller_amount' => number_format($netCentavos / 100, 2, '.', ''),
        ])->save();

        CommissionEntry::query()->create([
            'seller_order_id' => $sellerOrder->id,
            'store_id' => $store->id,
            'commission_rule_id' => $ruleData['rule']?->id,
            'gross_amount_centavos' => $grossCentavos,
            'rate_bps' => $ruleData['rate_bps'],
            'commission_centavos' => $commissionCentavos,
            'net_seller_centavos' => $netCentavos,
            'status' => 'recorded',
            'recorded_at' => $sellerOrder->paid_at ?? $now,
        ]);

        $saleKey = "sale:{$sellerOrder->id}";
        if (! SellerLedgerEntry::query()->where('idempotency_key', $saleKey)->exists()) {
            SellerLedgerEntry::query()->create([
                'store_id' => $store->id,
                'seller_order_id' => $sellerOrder->id,
                'seller_payout_id' => null,
                'actor_id' => $actor?->id,
                'entry_type' => LedgerEntryType::OrderEarning,
                'amount_centavos' => $netCentavos,
                'currency' => $sellerOrder->currency,
                'description' => "Net seller earnings for {$sellerOrder->seller_order_number} (rate ".number_format($ruleData['rate_bps'] / 100, 2).'%)',
                'idempotency_key' => $saleKey,
                'created_at' => $sellerOrder->paid_at ?? $now,
            ]);
        }
    }

    /**
     * Stamp holding maturity timestamp upon suborder delivery completion.
     */
    public function recordSuborderDelivered(SellerOrder $sellerOrder): void
    {
        $this->recordSuborderSettled($sellerOrder);

        $freshSuborder = $sellerOrder->fresh();
        if (! $freshSuborder instanceof SellerOrder || $freshSuborder->hold_until !== null) {
            return;
        }

        $freshSuborder->loadMissing('store');
        $rule = $freshSuborder->commission_rule_id !== null
            ? CommissionRule::query()->find($freshSuborder->commission_rule_id)
            : null;
        $holdDays = $rule instanceof CommissionRule
            ? $rule->hold_days
            : $this->activeRuleForStore($freshSuborder->store)['hold_days'];

        $deliveredAt = $freshSuborder->delivered_at ?? Date::now();
        $freshSuborder->forceFill([
            'hold_until' => $deliveredAt->addDays($holdDays),
        ])->save();
    }

    /**
     * Append compensating reversal entries when a paid/fulfilled suborder is cancelled or refunded.
     */
    public function recordSuborderReversed(SellerOrder $sellerOrder, ?User $actor = null, ?string $reason = null): void
    {
        /** @var CommissionEntry|null $entry */
        $entry = CommissionEntry::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->lockForUpdate()
            ->first();

        if (! $entry instanceof CommissionEntry || $entry->status === 'reversed') {
            return;
        }

        $now = Date::now();
        $entry->forceFill([
            'status' => 'reversed',
            'reversed_at' => $now,
        ])->save();

        $reversalKey = "reversal:{$sellerOrder->id}";
        if (! SellerLedgerEntry::query()->where('idempotency_key', $reversalKey)->exists()) {
            $noteSuffix = $reason !== null && trim($reason) !== '' ? ': '.trim($reason) : '';
            SellerLedgerEntry::query()->create([
                'store_id' => $sellerOrder->store_id,
                'seller_order_id' => $sellerOrder->id,
                'seller_payout_id' => null,
                'actor_id' => $actor?->id,
                'entry_type' => LedgerEntryType::OrderReversal,
                'amount_centavos' => -$entry->net_seller_centavos,
                'currency' => $sellerOrder->currency,
                'description' => "Reversal for {$sellerOrder->seller_order_number}{$noteSuffix}",
                'idempotency_key' => $reversalKey,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Reconcile store-scoped balances deterministically from append-only ledger entries and hold timestamps.
     *
     * @return array{
     *     inFulfillmentCentavos: int,
     *     inFulfillmentFormatted: string,
     *     onHoldCentavos: int,
     *     onHoldFormatted: string,
     *     maturedCentavos: int,
     *     maturedFormatted: string,
     *     availableCentavos: int,
     *     availableFormatted: string,
     *     pendingPayoutCentavos: int,
     *     pendingPayoutFormatted: string,
     *     paidOutCentavos: int,
     *     paidOutFormatted: string,
     *     grossConfirmedCentavos: int,
     *     grossConfirmedFormatted: string,
     *     commissionChargedCentavos: int,
     *     commissionChargedFormatted: string,
     *     minimumPayoutCentavos: int,
     *     minimumPayoutFormatted: string,
     *     commissionRateBps: int,
     *     commissionRatePercentFormatted: string,
     *     holdDays: int,
     * }
     */
    public function balancesForStore(Store $store, ?CarbonImmutable $asOf = null): array
    {
        $now = $asOf ?? Date::now();
        $ruleData = $this->activeRuleForStore($store);

        $orderLedgerSums = SellerLedgerEntry::query()
            ->where('store_id', $store->id)
            ->whereNotNull('seller_order_id')
            ->groupBy('seller_order_id')
            ->select([
                'seller_order_id',
                DB::raw('SUM(amount_centavos) as net_centavos'),
            ])
            ->get();

        $inFulfillmentCentavos = 0;
        $onHoldCentavos = 0;
        $maturedCentavos = 0;

        if ($orderLedgerSums->isNotEmpty()) {
            $orderIds = $orderLedgerSums->pluck('seller_order_id')->map(static fn (mixed $id): int => (int) $id)->all();
            $subordersById = SellerOrder::query()
                ->whereIn('id', $orderIds)
                ->get(['id', 'status', 'hold_until'])
                ->keyBy('id');

            foreach ($orderLedgerSums as $row) {
                $suborderId = (int) $row->getAttribute('seller_order_id');
                $netCentavos = (int) ($row->getAttribute('net_centavos') ?? 0);
                if ($netCentavos === 0) {
                    continue;
                }

                $suborder = $subordersById->get($suborderId);
                if (! $suborder instanceof SellerOrder) {
                    continue;
                }

                if (in_array($suborder->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped], true)) {
                    $inFulfillmentCentavos += $netCentavos;
                } elseif ($suborder->status === OrderStatus::Delivered) {
                    if ($suborder->hold_until !== null && $suborder->hold_until->greaterThan($now)) {
                        $onHoldCentavos += $netCentavos;
                    } else {
                        $maturedCentavos += $netCentavos;
                    }
                }
            }
        }

        $nonOrderLedgerCentavos = (int) (SellerLedgerEntry::query()
            ->where('store_id', $store->id)
            ->whereNull('seller_order_id')
            ->sum('amount_centavos') ?: 0);

        $availableCentavos = $maturedCentavos + $nonOrderLedgerCentavos;

        $pendingPayoutCentavos = (int) (SellerPayout::query()
            ->where('store_id', $store->id)
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Approved->value])
            ->sum('amount_centavos') ?: 0);

        $paidOutCentavos = (int) (SellerPayout::query()
            ->where('store_id', $store->id)
            ->where('status', PayoutStatus::Paid->value)
            ->sum('amount_centavos') ?: 0);

        $commissionTotals = CommissionEntry::query()
            ->where('store_id', $store->id)
            ->where('status', 'recorded')
            ->select([
                DB::raw('SUM(gross_amount_centavos) as gross_sum'),
                DB::raw('SUM(commission_centavos) as commission_sum'),
            ])
            ->first();

        $grossConfirmedCentavos = (int) ($commissionTotals?->getAttribute('gross_sum') ?? 0);
        $commissionChargedCentavos = (int) ($commissionTotals?->getAttribute('commission_sum') ?? 0);

        return [
            'inFulfillmentCentavos' => $inFulfillmentCentavos,
            'inFulfillmentFormatted' => self::formatCentavos($inFulfillmentCentavos),
            'onHoldCentavos' => $onHoldCentavos,
            'onHoldFormatted' => self::formatCentavos($onHoldCentavos),
            'maturedCentavos' => $maturedCentavos,
            'maturedFormatted' => self::formatCentavos($maturedCentavos),
            'availableCentavos' => $availableCentavos,
            'availableFormatted' => self::formatCentavos($availableCentavos),
            'pendingPayoutCentavos' => $pendingPayoutCentavos,
            'pendingPayoutFormatted' => self::formatCentavos($pendingPayoutCentavos),
            'paidOutCentavos' => $paidOutCentavos,
            'paidOutFormatted' => self::formatCentavos($paidOutCentavos),
            'grossConfirmedCentavos' => $grossConfirmedCentavos,
            'grossConfirmedFormatted' => self::formatCentavos($grossConfirmedCentavos),
            'commissionChargedCentavos' => $commissionChargedCentavos,
            'commissionChargedFormatted' => self::formatCentavos($commissionChargedCentavos),
            'minimumPayoutCentavos' => $ruleData['minimum_payout_centavos'],
            'minimumPayoutFormatted' => self::formatCentavos($ruleData['minimum_payout_centavos']),
            'commissionRateBps' => $ruleData['rate_bps'],
            'commissionRatePercentFormatted' => number_format($ruleData['rate_bps'] / 100, 2).'%',
            'holdDays' => $ruleData['hold_days'],
        ];
    }

    public static function formatCentavos(int $centavos, string $currency = 'PHP'): string
    {
        $prefix = $centavos < 0 ? '-' : '';
        $absoluteDecimal = number_format(abs($centavos) / 100, 2, '.', '');

        return $prefix.MoneyFormatter::format($absoluteDecimal, $currency);
    }
}
