<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Inventory\Actions\ImportInventoryCsvAction;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustInventoryStockRequest;
use App\Http\Requests\Admin\ImportInventoryCsvRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryLedgerService $ledger,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'filter' => ['nullable', 'string', 'in:all,low,out'],
            'movement_type' => ['nullable', 'string', 'in:reserved,released,adjusted,fulfilled'],
        ]);

        return Inertia::render('admin/Inventory', $this->ledger->ledgerView($request, $validated));
    }

    public function adjust(
        AdjustInventoryStockRequest $request,
        InventoryReservationService $inventory,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $product = Product::query()->findOrFail((int) $request->validated('product_id'));
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
            event: SecurityAuditEvent::InventoryAdjusted,
            user: null,
            actor: $actor,
            metadata: [
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
        ImportInventoryCsvRequest $request,
        ImportInventoryCsvAction $importAction,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $csvFile = $request->file('csv_file');
        if ($csvFile === null) {
            throw ValidationException::withMessages([
                'csv_file' => 'Please select a CSV file to upload.',
            ]);
        }

        $result = $importAction->execute($csvFile, $actor, $request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("Imported {$result['updatedCount']} SKU stock update(s)."),
        ]);

        return back();
    }

    public function template(): SymfonyResponse
    {
        return response($this->ledger->sampleTemplateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="xhopy-inventory-template.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
