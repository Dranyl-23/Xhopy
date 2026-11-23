<?php

namespace App\Domain\Seller\Policies;

use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerVerificationDocument;
use App\Models\User;

class SellerProfilePolicy
{
    public function view(User $user, SellerProfile $profile): bool
    {
        return $profile->user_id === $user->id || $user->canReviewSellers();
    }

    public function uploadDocument(User $user, SellerProfile $profile): bool
    {
        return $profile->user_id === $user->id && $profile->status->canBeEditedByApplicant();
    }

    public function deleteDocument(User $user, SellerProfile $profile, SellerVerificationDocument $document): bool
    {
        return $document->seller_profile_id === $profile->id
            && $profile->user_id === $user->id
            && $profile->status->canBeEditedByApplicant();
    }

    public function downloadDocument(User $user, SellerProfile $profile, SellerVerificationDocument $document): bool
    {
        if ($document->seller_profile_id !== $profile->id) {
            return false;
        }

        return $profile->user_id === $user->id || $user->canReviewSellers();
    }

    public function review(User $user): bool
    {
        return $user->canReviewSellers();
    }

    public function moderate(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
