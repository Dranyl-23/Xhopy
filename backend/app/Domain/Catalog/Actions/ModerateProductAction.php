<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Notifications\ProductModerationNotification;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ModerateProductAction
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    /**
     * Transition a seller/catalog product through Admin moderation review with mandatory rejection notes.
     */
    public function execute(
        User $actor,
        Product $product,
        ProductStatus $decision,
        ?string $reason = null,
        ?Request $request = null,
    ): Product {
        if (! in_array($decision, ProductStatus::adminModerationTargets(), true)) {
            throw new InvalidArgumentException("Unsupported moderation target status: {$decision->label()}.");
        }

        $cleanReason = is_string($reason) ? trim($reason) : '';
        if ($decision === ProductStatus::Rejected && $cleanReason === '') {
            throw new InvalidArgumentException('Please provide moderation feedback explaining what needs changes.');
        }

        $previousStatus = $product->status;

        $updated = DB::transaction(function () use ($actor, $product, $decision, $cleanReason): Product {
            $now = now();

            $product->forceFill([
                'status' => $decision,
                'moderation_notes' => $decision === ProductStatus::Active
                    ? null
                    : ($cleanReason !== '' ? $cleanReason : $product->moderation_notes),
                'moderated_at' => $now,
                'moderated_by' => $actor->id,
                'published_at' => $decision === ProductStatus::Active
                    ? ($product->published_at ?? $now)
                    : $product->published_at,
            ])->save();

            return $product->fresh(['store.owner', 'category', 'brand', 'moderator']) ?? $product;
        });

        $storeOwner = $updated->store instanceof Store && ! $updated->store->is_official
            ? $updated->store->owner
            : null;

        $auditEvent = $decision === ProductStatus::Active
            ? SecurityAuditEvent::AdminProductApproved
            : SecurityAuditEvent::AdminProductRejected;

        $this->auditLogger->record(
            event: $auditEvent,
            user: $storeOwner,
            actor: $actor,
            metadata: [
                'product_id' => $updated->id,
                'store_id' => $updated->store_id,
                'sku' => $updated->sku,
                'from_status' => $previousStatus->value,
                'to_status' => $decision->value,
                'reason' => $cleanReason !== '' ? $cleanReason : null,
            ],
            request: $request,
        );

        if ($storeOwner instanceof User) {
            $storeOwner->notify(ProductModerationNotification::fromProduct(
                $updated,
                $cleanReason !== '' ? $cleanReason : null,
            ));
        }

        return $updated;
    }
}
