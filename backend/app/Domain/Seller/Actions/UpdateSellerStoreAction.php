<?php

namespace App\Domain\Seller\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateSellerStoreAction
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Store $store, array $data, ?Request $request = null): Store
    {
        $updatedStore = DB::transaction(function () use ($store, $data): Store {
            $name = trim((string) $data['name']);
            $slug = $this->resolveUniqueSlug($name, $store);

            $store->fill([
                'name' => $name,
                'slug' => $slug,
                'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                    ? trim((string) $data['description'])
                    : null,
                'contact_email' => isset($data['contact_email']) && trim((string) $data['contact_email']) !== ''
                    ? trim((string) $data['contact_email'])
                    : null,
                'contact_phone' => isset($data['contact_phone']) && trim((string) $data['contact_phone']) !== ''
                    ? trim((string) $data['contact_phone'])
                    : null,
            ]);
            $store->save();

            // Keep denormalized seller_name synchronized across this store's catalog rows.
            Product::withTrashed()
                ->where('store_id', $store->id)
                ->update(['seller_name' => $store->name]);

            return $store->refresh();
        });

        $this->auditLogger->record(
            event: SecurityAuditEvent::SellerStoreUpdated,
            user: $actor,
            actor: $actor,
            metadata: [
                'store_id' => $updatedStore->id,
                'store_name' => $updatedStore->name,
                'slug' => $updatedStore->slug,
            ],
            request: $request,
        );

        return $updatedStore;
    }

    private function resolveUniqueSlug(string $storeName, Store $store): string
    {
        $base = Str::slug($storeName);
        if ($base === '') {
            $base = "store-{$store->id}";
        }

        $candidate = $base;
        $counter = 1;

        while (
            Store::query()
                ->where('slug', $candidate)
                ->where('id', '!=', $store->id)
                ->exists()
        ) {
            $counter++;
            $candidate = "{$base}-{$counter}";
        }

        return $candidate;
    }
}
