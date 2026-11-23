<?php

namespace App\Domain\Seller\Actions;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerStatusHistory;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Notifications\SellerApplicationStatusNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransitionSellerStatusAction
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    public function execute(
        User $actor,
        SellerProfile $profile,
        SellerStatus $nextStatus,
        ?string $reason = null,
        ?Request $request = null,
    ): SellerProfile {
        $trimmedReason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        if (in_array($nextStatus, [SellerStatus::Rejected, SellerStatus::Suspended], true) && $trimmedReason === null) {
            throw new InvalidArgumentException(
                sprintf('A reason is required when transitioning an application to %s.', $nextStatus->label())
            );
        }

        if ($nextStatus === SellerStatus::UnderReview && ! $actor->canReviewSellers()) {
            throw new InvalidArgumentException('You do not have permission to review seller applications.');
        }

        if ($nextStatus !== SellerStatus::UnderReview && ! $actor->isSuperAdmin()) {
            throw new InvalidArgumentException('Only a Super Admin can approve, reject, suspend, or reactivate sellers.');
        }

        return DB::transaction(function () use ($actor, $profile, $nextStatus, $trimmedReason, $request): SellerProfile {
            /** @var SellerProfile $locked */
            $locked = SellerProfile::query()
                ->whereKey($profile->id)
                ->with(['user', 'store'])
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $locked->status;
            if (! $fromStatus->canTransitionAdminTo($nextStatus, $actor->isSuperAdmin())) {
                throw new InvalidArgumentException(
                    sprintf('Cannot transition seller application from %s to %s.', $fromStatus->label(), $nextStatus->label())
                );
            }

            $now = Date::now();

            $locked->forceFill([
                'status' => $nextStatus,
                'reviewed_by' => $actor->id,
                'reviewed_at' => $now,
                'rejection_reason' => match ($nextStatus) {
                    SellerStatus::Rejected, SellerStatus::Suspended => $trimmedReason,
                    SellerStatus::Approved => null,
                    default => $locked->rejection_reason,
                },
                'approved_at' => $nextStatus === SellerStatus::Approved ? $now : $locked->approved_at,
                'suspended_at' => $nextStatus === SellerStatus::Suspended
                    ? $now
                    : ($nextStatus === SellerStatus::Approved ? null : $locked->suspended_at),
            ])->save();

            $store = $locked->store;
            if ($store instanceof Store) {
                $storeStatus = match ($nextStatus) {
                    SellerStatus::Approved => StoreStatus::Active,
                    SellerStatus::Suspended => StoreStatus::Suspended,
                    SellerStatus::Rejected => StoreStatus::Pending,
                    SellerStatus::Pending, SellerStatus::UnderReview => $store->status,
                };

                if ($store->status !== $storeStatus) {
                    $store->forceFill(['status' => $storeStatus])->save();
                }
            }

            SellerStatusHistory::query()->create([
                'seller_profile_id' => $locked->id,
                'actor_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $nextStatus,
                'reason' => $trimmedReason,
                'created_at' => $now,
            ]);

            $auditEvent = match ($nextStatus) {
                SellerStatus::UnderReview => SecurityAuditEvent::SellerUnderReview,
                SellerStatus::Approved => $fromStatus === SellerStatus::Suspended
                    ? SecurityAuditEvent::SellerReactivated
                    : SecurityAuditEvent::SellerApproved,
                SellerStatus::Rejected => SecurityAuditEvent::SellerRejected,
                SellerStatus::Suspended => SecurityAuditEvent::SellerSuspended,
                SellerStatus::Pending => SecurityAuditEvent::SellerApplicationSubmitted,
            };

            $applicant = $locked->user;

            $this->auditLogger->record(
                $auditEvent,
                user: $applicant,
                actor: $actor,
                metadata: [
                    'seller_profile_id' => $locked->id,
                    'store_id' => $locked->store_id,
                    'from_status' => $fromStatus->value,
                    'to_status' => $nextStatus->value,
                    'reason' => $trimmedReason,
                ],
                request: $request,
            );

            $updatedProfile = $locked->fresh(['user', 'store', 'reviewer', 'documents', 'statusHistory']) ?? $locked;

            if (in_array($nextStatus, [SellerStatus::Approved, SellerStatus::Rejected, SellerStatus::Suspended], true)) {
                DB::afterCommit(static function () use ($applicant, $updatedProfile, $trimmedReason): void {
                    $applicant->notify(SellerApplicationStatusNotification::fromProfile($updatedProfile, $trimmedReason));
                });
            }

            return $updatedProfile;
        });
    }
}
