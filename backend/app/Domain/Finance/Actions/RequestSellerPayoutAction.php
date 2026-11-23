<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\LedgerEntryType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Models\SellerLedgerEntry;
use App\Domain\Finance\Models\SellerPayout;
use App\Domain\Finance\Services\SellerFinanceService;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RequestSellerPayoutAction
{
    public function __construct(
        private readonly SellerFinanceService $financeService,
    ) {}

    /**
     * Atomically lock the store row, verify available matured balance & minimum withdrawal, and debit ledger.
     *
     * @throws ValidationException
     */
    public function execute(
        Store $store,
        User $actor,
        int $amountCentavos,
        string $payoutMethod,
        string $payoutDestination,
        ?string $requestNotes = null,
    ): SellerPayout {
        return DB::transaction(function () use (
            $store,
            $actor,
            $amountCentavos,
            $payoutMethod,
            $payoutDestination,
            $requestNotes,
        ): SellerPayout {
            /** @var Store $lockedStore */
            $lockedStore = Store::query()
                ->whereKey($store->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedStore->isActive()) {
                throw ValidationException::withMessages([
                    'amount' => 'Your store must be active before requesting a payout.',
                ]);
            }

            if ($lockedStore->is_official) {
                throw ValidationException::withMessages([
                    'amount' => 'Flagship official store balances are managed internally by platform accounting.',
                ]);
            }

            $balances = $this->financeService->balancesForStore($lockedStore);
            $minimumCentavos = $balances['minimumPayoutCentavos'];
            $availableCentavos = $balances['availableCentavos'];

            if ($amountCentavos < $minimumCentavos) {
                $minFormatted = SellerFinanceService::formatCentavos($minimumCentavos);

                throw ValidationException::withMessages([
                    'amount' => "Minimum withdrawal amount is {$minFormatted}.",
                ]);
            }

            if ($amountCentavos > $availableCentavos) {
                $availFormatted = SellerFinanceService::formatCentavos($availableCentavos);

                throw ValidationException::withMessages([
                    'amount' => "Requested amount exceeds your matured available balance ({$availFormatted}).",
                ]);
            }

            $now = Date::now();
            $payoutNumber = 'PAY-'.$now->format('Ymd').'-'.strtoupper(Str::random(6));

            $payout = SellerPayout::query()->create([
                'payout_number' => $payoutNumber,
                'store_id' => $lockedStore->id,
                'requested_by' => $actor->id,
                'status' => PayoutStatus::Pending,
                'currency' => 'PHP',
                'amount_centavos' => $amountCentavos,
                'payout_method' => trim($payoutMethod),
                'payout_destination' => trim($payoutDestination),
                'request_notes' => $requestNotes !== null && trim($requestNotes) !== '' ? trim($requestNotes) : null,
                'requested_at' => $now,
            ]);

            $formattedAmount = SellerFinanceService::formatCentavos($amountCentavos);

            SellerLedgerEntry::query()->create([
                'store_id' => $lockedStore->id,
                'seller_order_id' => null,
                'seller_payout_id' => $payout->id,
                'actor_id' => $actor->id,
                'entry_type' => LedgerEntryType::PayoutDebit,
                'amount_centavos' => -$amountCentavos,
                'currency' => 'PHP',
                'description' => "Payout request {$payoutNumber} ({$formattedAmount} via ".trim($payoutMethod).')',
                'idempotency_key' => "payout_debit:{$payout->id}",
                'created_at' => $now,
            ]);

            return $payout;
        });
    }
}
