<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Actions\SubmitSellerApplicationAction;
use App\Domain\Seller\Enums\SellerDocumentType;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerStatusHistory;
use App\Domain\Seller\Models\SellerVerificationDocument;
use App\Domain\Seller\Services\SellerDocumentStorageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SaveSellerApplicationRequest;
use App\Http\Requests\Seller\UploadSellerDocumentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardingController extends Controller
{
    /**
     * @var list<array{value: string, label: string}>
     */
    private const array BUSINESS_TYPES = [
        ['value' => 'sole_proprietorship', 'label' => 'Sole Proprietorship'],
        ['value' => 'partnership', 'label' => 'Partnership'],
        ['value' => 'corporation', 'label' => 'Corporation / Inc.'],
        ['value' => 'cooperative', 'label' => 'Cooperative'],
        ['value' => 'individual', 'label' => 'Individual Artisan / Creator'],
    ];

    public function show(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->canAccessAdminPanel()) {
            abort(403, 'Administrator and staff accounts cannot access seller onboarding.');
        }

        /** @var SellerProfile|null $profile */
        $profile = $user->sellerProfile()
            ->with(['store', 'documents', 'statusHistory.actor:id,name', 'reviewer:id,name'])
            ->first();

        return Inertia::render('seller/Onboarding', [
            'profile' => $profile instanceof SellerProfile ? [
                'id' => $profile->id,
                'status' => $profile->status->value,
                'status_label' => $profile->status->label(),
                'can_edit' => $profile->status->canBeEditedByApplicant(),
                'is_approved' => $user->hasApprovedStore(),
                'business_name' => $profile->business_name,
                'business_type' => $profile->business_type,
                'contact_phone' => $profile->contact_phone,
                'business_address' => $profile->business_address,
                'tax_identifier' => $profile->tax_identifier,
                'payout_notes' => $profile->payout_notes,
                'rejection_reason' => $profile->rejection_reason,
                'submitted_at' => $profile->submitted_at?->toDayDateTimeString(),
                'reviewed_at' => $profile->reviewed_at?->toDayDateTimeString(),
                'approved_at' => $profile->approved_at?->toDayDateTimeString(),
                'suspended_at' => $profile->suspended_at?->toDayDateTimeString(),
                'reviewer_name' => $profile->reviewer?->name,
                'store' => $profile->store !== null ? [
                    'id' => $profile->store->id,
                    'name' => $profile->store->name,
                    'slug' => $profile->store->slug,
                    'description' => $profile->store->description,
                    'contact_email' => $profile->store->contact_email,
                    'contact_phone' => $profile->store->contact_phone,
                    'status' => $profile->store->status->value,
                    'status_label' => $profile->store->status->label(),
                ] : null,
                'documents' => $profile->documents->map(static fn (SellerVerificationDocument $doc): array => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type->value,
                    'document_type_label' => $doc->document_type->label(),
                    'original_filename' => $doc->original_filename,
                    'mime_type' => $doc->mime_type,
                    'size_bytes' => $doc->size_bytes,
                    'created_at_diff' => $doc->created_at?->diffForHumans() ?? '',
                ])->values()->all(),
                'status_history' => $profile->statusHistory->map(static fn (SellerStatusHistory $entry): array => [
                    'id' => $entry->id,
                    'from_status' => $entry->from_status?->label(),
                    'to_status' => $entry->to_status->label(),
                    'to_status_value' => $entry->to_status->value,
                    'reason' => $entry->reason,
                    'actor_name' => $entry->actor !== null ? $entry->actor->name : 'System',
                    'created_at' => $entry->created_at->toDayDateTimeString(),
                ])->values()->all(),
            ] : null,
            'documentTypes' => array_map(
                static fn (SellerDocumentType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                SellerDocumentType::cases(),
            ),
            'businessTypes' => self::BUSINESS_TYPES,
            'defaultContactEmail' => $user->email,
            'defaultContactPhone' => $user->phone ?? '',
        ]);
    }

    public function store(
        SaveSellerApplicationRequest $request,
        SubmitSellerApplicationAction $action,
    ): RedirectResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $action->execute($user, $request->validated(), $request);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'store_name' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your seller application has been saved and submitted for review.'),
        ]);

        return to_route('seller.onboarding');
    }

    public function uploadDocument(
        UploadSellerDocumentRequest $request,
        SellerDocumentStorageService $storage,
    ): RedirectResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        /** @var SellerProfile|null $profile */
        $profile = $user->sellerProfile;
        if (! $profile instanceof SellerProfile) {
            throw ValidationException::withMessages([
                'document' => 'Submit your store application before uploading verification documents.',
            ]);
        }

        Gate::forUser($user)->authorize('uploadDocument', $profile);

        $file = $request->file('document');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'document' => 'Please select a document file to upload.',
            ]);
        }

        $docType = SellerDocumentType::from((string) $request->validated('document_type'));

        try {
            $storage->store($profile, $docType, $file);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'document' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Verification document uploaded securely.'),
        ]);

        return to_route('seller.onboarding');
    }

    public function downloadDocument(
        Request $request,
        SellerVerificationDocument $document,
        SellerDocumentStorageService $storage,
        SecurityAuditLogger $auditLogger,
    ): StreamedResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $document->loadMissing('sellerProfile');
        $profile = $document->sellerProfile;

        Gate::forUser($user)->authorize('downloadDocument', [$profile, $document]);

        $auditLogger->record(
            SecurityAuditEvent::SellerDocumentDownloaded,
            user: $profile->user,
            actor: $user,
            metadata: [
                'seller_profile_id' => $profile->id,
                'document_id' => $document->id,
            ],
            request: $request,
        );

        return $storage->download($document);
    }

    public function destroyDocument(
        Request $request,
        SellerVerificationDocument $document,
        SellerDocumentStorageService $storage,
    ): RedirectResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $document->loadMissing('sellerProfile');
        $profile = $document->sellerProfile;

        Gate::forUser($user)->authorize('deleteDocument', [$profile, $document]);

        $storage->delete($document);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Verification document removed.'),
        ]);

        return to_route('seller.onboarding');
    }
}
