<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Inventory\Actions\ImportInventoryCsvAction;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Seller\Services\SellerWorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\AdjustSellerInventoryStockRequest;
use App\Http\Requests\Seller\ImportSellerInventoryCsvRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly SellerWorkspaceService $workspace,
        private readonly InventoryLedgerService $ledger,
    ) {}

    public function index(Request $request): Response
    {
        ['store' => $store] = $this->workspace->requireApprovedWorkspace($request->user());

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'filter' => ['nullable', 'string', 'in:all,low,out'],
            'movement_type' => ['nullable', 'string', 'in:reserved,released,adjusted,fulfilled'],
        ]);

        return Inertia::render('seller/Inventory', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
            ],
            ...$this->ledger->ledgerView($request, $validated, $store->id),
        ]);
    }

    public function adjust(
        AdjustSellerInventoryStockRequest $request,
        InventoryReservationService $inventory,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        ['store' => $store] = $this->workspace->requireApprovedWorkspace($actor);

        $product = Product::query()->findOrFail((int) $request->validated('product_id'));
        Gate::forUser($actor)->authorize('manageOwned', $product);

        $variantId = $request->validated('product_variant_id') !== null
            ? (int) $request->validated('product_variant_id')
            : null;

        $variant = null;
        if ($variantId !== null) {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->find($variantId);

            if (! $variant instanceof ProductVariant) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected variant does not belong to this product.',
                ]);
            }
        }

        $movement = $inventory->adjustStock(
            product: $product,
            variant: $variant,
            targetQuantity: (int) $request->validated('stock_quantity'),
            actor: $actor,
            reference: (string) $request->validated('reference'),
        );

        $auditLogger->record(
            event: SecurityAuditEvent::SellerInventoryAdjusted,
            user: $actor,
            actor: $actor,
            metadata: [
                'store_id' => $store->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'sku' => $variant->sku ?? $product->sku,
                'quantity_delta' => $movement->quantity_delta,
                'stock_after' => $movement->stock_after,
                'reference' => $movement->reference,
            ],
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stock quantity updated to :qty.', ['qty' => (string) $movement->stock_after]),
        ]);

        return back();
    }

    public function importCsv(
        ImportSellerInventoryCsvRequest $request,
        ImportInventoryCsvAction $importAction,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        ['store' => $store] = $this->workspace->requireApprovedWorkspace($actor);

        $csvFile = $request->file('csv_file');
        if ($csvFile === null) {
            throw ValidationException::withMessages([
                'csv_file' => 'Please select a CSV file to upload.',
            ]);
        }

        $result = $importAction->execute($csvFile, $actor, $request, $store);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("Imported {$result['updatedCount']} SKU stock update(s) for {$store->name}."),
        ]);

        return back();
    }

    public function template(Request $request): SymfonyResponse
    {
        ['store' => $store] = $this->workspace->requireApprovedWorkspace($request->user());

        return response($this->ledger->sampleTemplateCsv($store->id), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="xhopy-store-inventory-template.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
