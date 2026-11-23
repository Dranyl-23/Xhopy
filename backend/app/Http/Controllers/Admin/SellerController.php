<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Actions\TransitionSellerStatusAction;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerStatusHistory;
use App\Domain\Seller\Models\SellerVerificationDocument;
use App\Domain\Seller\Services\SellerDocumentStorageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TransitionSellerStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User || ! $actor->canReviewSellers()) {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::enum(SellerStatus::class)],
        ]);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $statusFilter = isset($validated['status']) ? (string) $validated['status'] : '';

        $query = SellerProfile::query()
            ->with(['user:id,name,email', 'store:id,name,slug,status'])
            ->withCount('documents')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'under_review' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
            ->orderByDesc('id');

        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($builder) use ($escaped): void {
                $builder->where('business_name', 'like', '%'.$escaped.'%')
                    ->orWhereHas('store', static fn ($storeQuery) => $storeQuery->where('name', 'like', '%'.$escaped.'%'))
                    ->orWhereHas('user', static fn ($userQuery) => $userQuery
                        ->where('name', 'like', '%'.$escaped.'%')
                        ->orWhere('email', 'like', '%'.$escaped.'%'));
            });
        }

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $sellers = $query->paginate(20)->withQueryString()->through(static fn (SellerProfile $profile): array => [
            'id' => $profile->id,
            'business_name' => $profile->business_name,
            'business_type' => $profile->business_type,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
            'documents_count' => (int) ($profile->getAttribute('documents_count') ?? 0),
            'applicant' => [
                'id' => $profile->user->id,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
            ],
            'store' => $profile->store !== null ? [
                'id' => $profile->store->id,
                'name' => $profile->store->name,
                'slug' => $profile->store->slug,
                'status' => $profile->store->status->value,
                'status_label' => $profile->store->status->label(),
            ] : null,
            'submitted_at' => $profile->submitted_at?->toDayDateTimeString(),
            'submitted_at_diff' => $profile->submitted_at?->diffForHumans() ?? '',
        ]);

        $rawCounts = SellerProfile::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'pending' => (int) ($rawCounts[SellerStatus::Pending->value] ?? 0),
            'under_review' => (int) ($rawCounts[SellerStatus::UnderReview->value] ?? 0),
            'approved' => (int) ($rawCounts[SellerStatus::Approved->value] ?? 0),
            'rejected' => (int) ($rawCounts[SellerStatus::Rejected->value] ?? 0),
            'suspended' => (int) ($rawCounts[SellerStatus::Suspended->value] ?? 0),
        ];

        $statuses = array_map(
            static fn (SellerStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            SellerStatus::cases(),
        );

        return Inertia::render('admin/sellers/Index', [
            'sellers' => $sellers,
            'summary' => $summary,
            'statuses' => $statuses,
            'canModerate' => $actor->isSuperAdmin(),
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function show(Request $request, SellerProfile $sellerProfile): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        Gate::forUser($actor)->authorize('view', $sellerProfile);

        $sellerProfile->load([
            'user:id,name,email,phone,email_verified_at,created_at',
            'store',
            'reviewer:id,name',
            'documents',
            'statusHistory.actor:id,name',
        ]);

        $allowedTransitions = array_map(
            static fn (SellerStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'requires_reason' => in_array($status, [SellerStatus::Rejected, SellerStatus::Suspended], true),
            ],
            $sellerProfile->status->allowedAdminTransitions($actor->isSuperAdmin()),
        );

        return Inertia::render('admin/sellers/Show', [
            'seller' => [
                'id' => $sellerProfile->id,
                'business_name' => $sellerProfile->business_name,
                'business_type' => $sellerProfile->business_type,
                'contact_phone' => $sellerProfile->contact_phone,
                'business_address' => $sellerProfile->business_address,
                'tax_identifier' => $sellerProfile->tax_identifier,
                'payout_notes' => $sellerProfile->payout_notes,
                'status' => $sellerProfile->status->value,
                'status_label' => $sellerProfile->status->label(),
                'rejection_reason' => $sellerProfile->rejection_reason,
                'submitted_at' => $sellerProfile->submitted_at?->toDayDateTimeString(),
                'reviewed_at' => $sellerProfile->reviewed_at?->toDayDateTimeString(),
                'approved_at' => $sellerProfile->approved_at?->toDayDateTimeString(),
                'suspended_at' => $sellerProfile->suspended_at?->toDayDateTimeString(),
                'reviewer_name' => $sellerProfile->reviewer?->name,
                'applicant' => [
                    'id' => $sellerProfile->user->id,
                    'name' => $sellerProfile->user->name,
                    'email' => $sellerProfile->user->email,
                    'phone' => $sellerProfile->user->phone,
                    'email_verified' => $sellerProfile->user->hasVerifiedEmail(),
                    'joined_at' => $sellerProfile->user->created_at?->toDayDateTimeString(),
                ],
                'store' => $sellerProfile->store !== null ? [
                    'id' => $sellerProfile->store->id,
                    'name' => $sellerProfile->store->name,
                    'slug' => $sellerProfile->store->slug,
                    'description' => $sellerProfile->store->description,
                    'contact_email' => $sellerProfile->store->contact_email,
                    'contact_phone' => $sellerProfile->store->contact_phone,
                    'status' => $sellerProfile->store->status->value,
                    'status_label' => $sellerProfile->store->status->label(),
                ] : null,
                'documents' => $sellerProfile->documents->map(static fn (SellerVerificationDocument $doc): array => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type->value,
                    'document_type_label' => $doc->document_type->label(),
                    'original_filename' => $doc->original_filename,
                    'mime_type' => $doc->mime_type,
                    'size_bytes' => $doc->size_bytes,
                    'created_at_diff' => $doc->created_at?->diffForHumans() ?? '',
                ])->values()->all(),
                'status_history' => $sellerProfile->statusHistory->map(static fn (SellerStatusHistory $entry): array => [
                    'id' => $entry->id,
                    'from_status' => $entry->from_status?->label(),
                    'to_status' => $entry->to_status->label(),
                    'to_status_value' => $entry->to_status->value,
                    'reason' => $entry->reason,
                    'actor_name' => $entry->actor !== null ? $entry->actor->name : 'System',
                    'created_at' => $entry->created_at->toDayDateTimeString(),
                ])->values()->all(),
            ],
            'allowedTransitions' => $allowedTransitions,
            'canModerate' => $actor->isSuperAdmin(),
        ]);
    }

    public function transition(
        TransitionSellerStatusRequest $request,
        SellerProfile $sellerProfile,
        TransitionSellerStatusAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $nextStatus = SellerStatus::from((string) $request->validated('status'));
        $reason = $request->validated('reason');

        if ($nextStatus !== SellerStatus::UnderReview && ! $actor->isSuperAdmin()) {
            abort(403, 'Only a Super Admin can approve, reject, suspend, or reactivate sellers.');
        }

        try {
            $action->execute(
                actor: $actor,
                profile: $sellerProfile,
                nextStatus: $nextStatus,
                reason: is_string($reason) ? $reason : null,
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'status' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Updated seller application status to :status.', [
                'status' => $nextStatus->label(),
            ]),
        ]);

        return to_route('admin.sellers.show', $sellerProfile);
    }

    public function downloadDocument(
        Request $request,
        SellerProfile $sellerProfile,
        SellerVerificationDocument $document,
        SellerDocumentStorageService $storage,
        SecurityAuditLogger $auditLogger,
    ): StreamedResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        Gate::forUser($actor)->authorize('downloadDocument', [$sellerProfile, $document]);

        $sellerProfile->loadMissing('user');

        $auditLogger->record(
            SecurityAuditEvent::SellerDocumentDownloaded,
            user: $sellerProfile->user,
            actor: $actor,
            metadata: [
                'seller_profile_id' => $sellerProfile->id,
                'document_id' => $document->id,
            ],
            request: $request,
        );

        return $storage->download($document);
    }
}
