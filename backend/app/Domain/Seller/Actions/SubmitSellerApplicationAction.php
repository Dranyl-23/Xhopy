<?php

namespace App\Domain\Seller\Actions;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerStatusHistory;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SubmitSellerApplicationAction
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data, ?Request $request = null): SellerProfile
    {
        if ($user->canAccessAdminPanel()) {
            throw new InvalidArgumentException('Administrator and staff accounts cannot apply as marketplace sellers.');
        }

        return DB::transaction(function () use ($user, $data, $request): SellerProfile {
            /** @var SellerProfile|null $existingProfile */
            $existingProfile = SellerProfile::query()
                ->where('user_id', $user->id)
                ->with('store')
                ->lockForUpdate()
                ->first();

            if ($existingProfile instanceof SellerProfile && ! $existingProfile->status->canBeEditedByApplicant()) {
                throw new InvalidArgumentException(
                    sprintf('Applications in "%s" status cannot be edited.', $existingProfile->status->label())
                );
            }

            $storeName = trim((string) $data['store_name']);
            $requestedSlug = trim((string) ($data['store_slug'] ?? ''));
            $storeSlug = $this->resolveUniqueStoreSlug(
                $requestedSlug !== '' ? $requestedSlug : $storeName,
                $existingProfile?->store_id,
            );

            $contactPhone = trim((string) $data['contact_phone']);
            $contactEmail = isset($data['contact_email']) && trim((string) $data['contact_email']) !== ''
                ? trim((string) $data['contact_email'])
                : $user->email;
            $description = isset($data['store_description']) && trim((string) $data['store_description']) !== ''
                ? trim((string) $data['store_description'])
                : null;

            $store = $existingProfile?->store;
            if (! $store instanceof Store) {
                $store = new Store;
                $store->owner_user_id = $user->id;
                $store->is_official = false;
            }

            $store->fill([
                'owner_user_id' => $user->id,
                'name' => $storeName,
                'slug' => $storeSlug,
                'description' => $description,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'status' => StoreStatus::Pending,
                'is_official' => false,
            ]);
            $store->save();

            StoreMembership::query()->firstOrCreate(
                [
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'owner',
                ],
            );

            $previousStatus = $existingProfile?->status;
            $profile = $existingProfile ?? new SellerProfile(['user_id' => $user->id]);

            $taxIdentifier = isset($data['tax_identifier']) && trim((string) $data['tax_identifier']) !== ''
                ? trim((string) $data['tax_identifier'])
                : null;
            $payoutNotes = isset($data['payout_notes']) && trim((string) $data['payout_notes']) !== ''
                ? trim((string) $data['payout_notes'])
                : null;

            $profile->fill([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'business_name' => trim((string) $data['business_name']),
                'business_type' => trim((string) $data['business_type']),
                'contact_phone' => $contactPhone,
                'business_address' => trim((string) $data['business_address']),
                'tax_identifier' => $taxIdentifier,
                'payout_notes' => $payoutNotes,
                'status' => SellerStatus::Pending,
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => $profile->submitted_at ?? Date::now(),
            ]);
            $profile->save();

            if ($previousStatus === null || $previousStatus === SellerStatus::Rejected) {
                SellerStatusHistory::query()->create([
                    'seller_profile_id' => $profile->id,
                    'actor_id' => $user->id,
                    'from_status' => $previousStatus,
                    'to_status' => SellerStatus::Pending,
                    'reason' => $previousStatus === SellerStatus::Rejected
                        ? 'Seller updated details and resubmitted application.'
                        : 'Initial seller onboarding application submitted.',
                    'created_at' => Date::now(),
                ]);
            }

            if ($user->role !== UserRole::Seller) {
                $user->forceFill(['role' => UserRole::Seller])->save();
            }

            $this->auditLogger->record(
                SecurityAuditEvent::SellerApplicationSubmitted,
                user: $user,
                actor: $user,
                metadata: [
                    'seller_profile_id' => $profile->id,
                    'store_id' => $store->id,
                    'store_slug' => $store->slug,
                    'resubmission' => $previousStatus === SellerStatus::Rejected,
                ],
                request: $request,
            );

            return $profile->fresh(['store', 'documents', 'statusHistory']) ?? $profile;
        });
    }

    private function resolveUniqueStoreSlug(string $source, ?int $ignoreStoreId = null): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'seller-store';
        }

        $base = mb_substr($base, 0, 120);
        $candidate = $base;
        $suffix = 2;

        while (
            Store::query()
                ->where('slug', $candidate)
                ->when($ignoreStoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreStoreId))
                ->exists()
        ) {
            $candidate = mb_substr($base, 0, 110).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
