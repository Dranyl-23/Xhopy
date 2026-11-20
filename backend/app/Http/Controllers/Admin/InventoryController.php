<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Inventory\Actions\ImportInventoryCsvAction;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustInventoryStockRequest;
use App\Http\Requests\Admin\ImportInventoryCsvRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'filter' => ['nullable', 'string', 'in:all,low,out'],
            'movement_type' => ['nullable', 'string', 'in:reserved,released,adjusted,fulfilled'],
        ]);

        $search = isset($validated['q']) ? mb_strtolower(trim((string) $validated['q'])) : '';
        $stockFilter = isset($validated['filter']) ? (string) $validated['filter'] : 'all';
        $movementType = isset($validated['movement_type']) ? (string) $validated['movement_type'] : '';

        $products = Product::query()
            ->with(['category:id,name', 'variants' => static fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get();

        $allStockRows = [];
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($products as $product) {
            if ($product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $qty = (int) $variant->stock_quantity;
                    if ($product->status === ProductStatus::Active && $variant->is_active) {
                        if ($qty <= 0) {
                            $outOfStockCount++;
                        } elseif ($qty <= 5) {
                            $lowStockCount++;
                        }
                    }

                    $allStockRows[] = [
                        'key' => "v-{$variant->id}",
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'name' => $product->name,
                        'variant_title' => $variant->title,
                        'sku' => $variant->sku,
                        'category_name' => $product->category->name,
                        'status_label' => $product->status->label(),
                        'stock_quantity' => $qty,
                    ];
                }

                continue;
            }

            $qty = (int) $product->stock_quantity;
            if ($product->status === ProductStatus::Active) {
                if ($qty <= 0) {
                    $outOfStockCount++;
                } elseif ($qty <= 5) {
                    $lowStockCount++;
                }
            }

            $allStockRows[] = [
                'key' => "p-{$product->id}",
                'product_id' => $product->id,
                'product_variant_id' => null,
                'name' => $product->name,
                'variant_title' => null,
                'sku' => $product->sku,
                'category_name' => $product->category->name,
                'status_label' => $product->status->label(),
                'stock_quantity' => $qty,
            ];
        }

        $filteredRows = array_values(array_filter($allStockRows, static function (array $row) use ($search, $stockFilter): bool {
            if ($stockFilter === 'low' && $row['stock_quantity'] > 5) {
                return false;
            }
            if ($stockFilter === 'out' && $row['stock_quantity'] > 0) {
                return false;
            }
            if ($search !== '') {
                $haystack = mb_strtolower($row['name'].' '.($row['variant_title'] ?? '').' '.$row['sku']);

                return str_contains($haystack, $search);
            }

            return true;
        }));

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 15;
        $slicedRows = array_slice($filteredRows, ($page - 1) * $perPage, $perPage);

        $skuPaginator = new LengthAwarePaginator(
            $slicedRows,
            count($filteredRows),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        $movementsQuery = StockMovement::query()
            ->with(['product:id,name,sku', 'variant:id,title,sku', 'order:id,order_number', 'actor:id,name'])
            ->orderByDesc('id');

        if ($movementType !== '') {
            $movementsQuery->where('type', $movementType);
        }

        $movements = $movementsQuery->limit(30)->get()->map(static fn (StockMovement $movement): array => [
            'id' => $movement->id,
            'product_name' => $movement->product->name,
            'variant_title' => $movement->variant?->title,
            'sku' => $movement->variant->sku ?? $movement->product->sku,
            'type' => $movement->type->value,
            'type_label' => ucfirst($movement->type->value),
            'quantity_delta' => $movement->quantity_delta,
            'stock_after' => $movement->stock_after,
            'reference' => $movement->reference ?? '—',
            'order_id' => $movement->order_id,
            'order_number' => $movement->order?->order_number,
            'actor_name' => $movement->actor->name ?? 'System',
            'created_at_diff' => $movement->created_at->diffForHumans(),
        ])->values()->all();

        return Inertia::render('admin/Inventory', [
            'skus' => $skuPaginator,
            'movements' => $movements,
            'summary' => [
                'totalSkus' => count($allStockRows),
                'lowStockCount' => $lowStockCount,
                'outOfStockCount' => $outOfStockCount,
            ],
            'filters' => [
                'q' => $search,
                'filter' => $stockFilter,
                'movement_type' => $movementType,
            ],
        ]);
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
        $firstVariant = ProductVariant::query()->first();
        $sampleSku = $firstVariant instanceof ProductVariant
            ? $firstVariant->sku
            : (Product::query()->value('sku') ?? 'XH-SAMPLE-001');

        $csv = "sku,stock_quantity,reason\n{$sampleSku},25,Cycle count restock\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="xhopy-inventory-template.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
