<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\LedgerEntryType;
use App\Domain\Finance\Enums\PayoutStatus;
use App\Domain\Finance\Models\SellerLedgerEntry;
use App\Domain\Finance\Models\SellerPayout;
use App\Domain\Finance\Services\SellerFinanceService;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReviewSellerPayoutAction
{
    /**
     * Transition a seller payout request cleanly and credit back funds if rejected.
     *
     * @throws ValidationException
     */
    public function execute(
        SellerPayout $payout,
        PayoutStatus $nextStatus,
        User $reviewer,
        ?string $reviewNotes = null,
        ?string $externalReference = null,
    ): SellerPayout {
        return DB::transaction(function () use (
            $payout,
            $nextStatus,
            $reviewer,
            $reviewNotes,
            $externalReference,
        ): SellerPayout {
            /** @var SellerPayout $lockedPayout */
            $lockedPayout = SellerPayout::query()
                ->whereKey($payout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPayout->status->canTransitionTo($nextStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Payout {$lockedPayout->payout_number} cannot transition from {$lockedPayout->status->label()} to {$nextStatus->label()}.",
                ]);
            }

            $now = Date::now();
            $cleanNotes = $reviewNotes !== null && trim($reviewNotes) !== '' ? trim($reviewNotes) : null;
            $cleanReference = $externalReference !== null && trim($externalReference) !== '' ? trim($externalReference) : null;

            if ($nextStatus === PayoutStatus::Rejected && $cleanNotes === null) {
                throw ValidationException::withMessages([
                    'review_notes' => 'Please provide a rejection reason so the seller understands the decision.',
                ]);
            }

            $updates = [
                'status' => $nextStatus,
                'reviewed_by' => $reviewer->id,
                'review_notes' => $cleanNotes ?? $lockedPayout->review_notes,
            ];

            if ($nextStatus === PayoutStatus::Approved && $lockedPayout->approved_at === null) {
                $updates['approved_at'] = $now;
            }

            if ($nextStatus === PayoutStatus::Paid) {
                if ($lockedPayout->approved_at === null) {
                    $updates['approved_at'] = $now;
                }
                $updates['paid_at'] = $now;
                if ($cleanReference !== null) {
                    $updates['external_reference'] = mb_substr($cleanReference, 0, 120);
                }
            }

            if ($nextStatus === PayoutStatus::Rejected) {
                $updates['rejected_at'] = $now;
            }

            $lockedPayout->forceFill($updates)->save();

            if ($nextStatus === PayoutStatus::Rejected) {
                $rejectKey = "payout_reject:{$lockedPayout->id}";
                if (! SellerLedgerEntry::query()->where('idempotency_key', $rejectKey)->exists()) {
                    $formattedAmount = SellerFinanceService::formatCentavos($lockedPayout->amount_centavos);
                    SellerLedgerEntry::query()->create([
                        'store_id' => $lockedPayout->store_id,
                        'seller_order_id' => null,
                        'seller_payout_id' => $lockedPayout->id,
                        'actor_id' => $reviewer->id,
                        'entry_type' => LedgerEntryType::PayoutRejectionCredit,
                        'amount_centavos' => $lockedPayout->amount_centavos,
                        'currency' => $lockedPayout->currency,
                        'description' => "Payout {$lockedPayout->payout_number} rejected ({$formattedAmount} returned): {$cleanNotes}",
                        'idempotency_key' => $rejectKey,
                        'created_at' => $now,
                    ]);
                }
            }

            return $lockedPayout->refresh();
        });
    }
}
