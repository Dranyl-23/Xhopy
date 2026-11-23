<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\CommissionRule;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class UpdateCommissionRuleAction
{
    /**
     * Create a new active commission rule version cleanly without mutating historical order snapshots.
     */
    public function execute(
        ?Store $store,
        int $rateBps,
        int $holdDays,
        int $minimumPayoutCentavos,
        User $actor,
        ?string $notes = null,
    ): CommissionRule {
        return DB::transaction(function () use (
            $store,
            $rateBps,
            $holdDays,
            $minimumPayoutCentavos,
            $actor,
            $notes,
        ): CommissionRule {
            $query = CommissionRule::query()->where('is_active', true);
            if ($store instanceof Store) {
                $query->where('store_id', $store->id);
            } else {
                $query->whereNull('store_id');
            }

            $query->update(['is_active' => false]);

            return CommissionRule::query()->create([
                'store_id' => $store?->id,
                'rate_bps' => max(0, min(10000, $rateBps)),
                'hold_days' => max(0, min(90, $holdDays)),
                'minimum_payout_centavos' => max(100, $minimumPayoutCentavos),
                'is_active' => true,
                'effective_from' => Date::now(),
                'created_by' => $actor->id,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            ]);
        });
    }
}
