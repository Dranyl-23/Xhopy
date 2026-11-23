<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class InventoryLedgerService
{
    /**
     * Build paginated SKU inventory levels, stock movement history, and summary counts.
     *
     * @param  array{q?: string|null, filter?: string|null, movement_type?: string|null}  $filters
     * @return array{
     *     skus: LengthAwarePaginator<int, array<string, mixed>>,
     *     movements: list<array<string, mixed>>,
     *     summary: array{totalSkus: int, lowStockCount: int, outOfStockCount: int},
     *     filters: array{q: string, filter: string, movement_type: string}
     * }
     */
    public function ledgerView(Request $request, array $filters, ?int $storeId = null): array
    {
        $search = isset($filters['q']) ? mb_strtolower(trim((string) $filters['q'])) : '';
        $stockFilter = isset($filters['filter']) ? (string) $filters['filter'] : 'all';
        $movementType = isset($filters['movement_type']) ? (string) $filters['movement_type'] : '';

        $productsQuery = Product::query()
            ->with(['category:id,name', 'variants' => static fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name');

        if ($storeId !== null) {
            $productsQuery->where('store_id', $storeId);
        }

        $products = $productsQuery->get();

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

        /** @var LengthAwarePaginator<int, array<string, mixed>> $skuPaginator */
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
            ->with(['product:id,name,sku,store_id', 'variant:id,title,sku', 'order:id,order_number', 'actor:id,name'])
            ->orderByDesc('id');

        if ($storeId !== null) {
            $movementsQuery->whereHas('product', static fn ($productQuery) => $productQuery->where('store_id', $storeId));
        }

        if ($movementType !== '') {
            $movementsQuery->where('type', $movementType);
        }

        $movements = array_values($movementsQuery->limit(30)->get()->map(static fn (StockMovement $movement): array => [
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
        ])->all());

        return [
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
        ];
    }

    public function sampleTemplateCsv(?int $storeId = null): string
    {
        $variantQuery = ProductVariant::query();
        if ($storeId !== null) {
            $variantQuery->whereHas('product', static fn ($q) => $q->where('store_id', $storeId));
        }

        $firstVariant = $variantQuery->first();
        if ($firstVariant instanceof ProductVariant) {
            $sampleSku = $firstVariant->sku;
        } else {
            $productQuery = Product::query();
            if ($storeId !== null) {
                $productQuery->where('store_id', $storeId);
            }
            $rawSku = $productQuery->value('sku');
            $sampleSku = is_string($rawSku) && $rawSku !== '' ? $rawSku : 'XH-SAMPLE-001';
        }

        return "sku,stock_quantity,reason\n{$sampleSku},25,Cycle count restock\n";
    }
}
