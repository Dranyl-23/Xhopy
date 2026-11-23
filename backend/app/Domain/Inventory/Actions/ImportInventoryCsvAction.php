<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Seller\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImportInventoryCsvAction
{
    private const int MAX_ROWS = 500;

    public function __construct(
        private readonly InventoryReservationService $inventoryReservation,
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    /**
     * Validate and apply stock quantity adjustments atomically from an uploaded CSV file.
     *
     * @return array{updatedCount: int, rowCount: int}
     *
     * @throws ValidationException
     */
    public function execute(
        UploadedFile $file,
        User $actor,
        ?Request $request = null,
        ?Store $scopedStore = null,
    ): array {
        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'csv_file' => 'Unable to read the uploaded CSV file.',
            ]);
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'csv_file' => 'Unable to open the uploaded CSV file.',
            ]);
        }

        try {
            $header = fgetcsv($handle);
            if (! is_array($header) || $header === [null]) {
                throw ValidationException::withMessages([
                    'csv_file' => 'CSV file must contain a header row (sku,stock_quantity,reason).',
                ]);
            }

            $normalizedHeader = array_map(
                static fn (?string $col): string => mb_strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $col))),
                $header,
            );

            $skuIndex = array_search('sku', $normalizedHeader, true);
            $stockIndex = array_search('stock_quantity', $normalizedHeader, true);
            $reasonIndex = array_search('reason', $normalizedHeader, true);

            if ($skuIndex === false || $stockIndex === false) {
                throw ValidationException::withMessages([
                    'csv_file' => 'CSV header must include "sku" and "stock_quantity" columns.',
                ]);
            }

            $rows = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages([
                        'csv_file' => 'CSV import exceeds the maximum limit of '.self::MAX_ROWS.' rows per upload.',
                    ]);
                }

                $rawSku = trim((string) ($row[$skuIndex] ?? ''));
                $rawQty = trim((string) ($row[$stockIndex] ?? ''));
                $rawReason = $reasonIndex !== false ? trim((string) ($row[$reasonIndex] ?? '')) : '';

                if ($rawSku === '') {
                    throw ValidationException::withMessages([
                        'csv_file' => "Line {$lineNumber}: SKU is required.",
                    ]);
                }

                if ($this->hasSpreadsheetFormulaPrefix($rawSku) || $this->hasSpreadsheetFormulaPrefix($rawReason)) {
                    throw ValidationException::withMessages([
                        'csv_file' => "Line {$lineNumber}: Unsafe leading spreadsheet formula character detected.",
                    ]);
                }

                if (! ctype_digit($rawQty) || (int) $rawQty > 100000) {
                    throw ValidationException::withMessages([
                        'csv_file' => "Line {$lineNumber}: stock_quantity for SKU \"{$rawSku}\" must be a whole integer between 0 and 100000.",
                    ]);
                }

                $rows[] = [
                    'line' => $lineNumber,
                    'sku' => mb_strtoupper($rawSku),
                    'stock_quantity' => (int) $rawQty,
                    'reason' => $rawReason !== '' ? $rawReason : 'CSV stock import',
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'csv_file' => 'CSV file contains no data rows to process.',
                ]);
            }

            return DB::transaction(function () use ($rows, $actor, $request, $scopedStore): array {
                $updatedCount = 0;

                foreach ($rows as $row) {
                    $variant = ProductVariant::query()->with('product')->where('sku', $row['sku'])->first();

                    if ($variant instanceof ProductVariant) {
                        if ($scopedStore instanceof Store && (int) $variant->product->store_id !== $scopedStore->id) {
                            throw ValidationException::withMessages([
                                'csv_file' => "Line {$row['line']}: SKU \"{$row['sku']}\" does not belong to your store.",
                            ]);
                        }

                        $this->inventoryReservation->adjustStock(
                            product: $variant->product,
                            variant: $variant,
                            targetQuantity: $row['stock_quantity'],
                            actor: $actor,
                            reference: $row['reason'],
                        );
                        $updatedCount++;

                        continue;
                    }

                    $product = Product::query()->where('sku', $row['sku'])->first();
                    if (! $product instanceof Product) {
                        throw ValidationException::withMessages([
                            'csv_file' => "Line {$row['line']}: SKU \"{$row['sku']}\" was not found in catalog products or variants.",
                        ]);
                    }

                    if ($scopedStore instanceof Store && (int) $product->store_id !== $scopedStore->id) {
                        throw ValidationException::withMessages([
                            'csv_file' => "Line {$row['line']}: SKU \"{$row['sku']}\" does not belong to your store.",
                        ]);
                    }

                    if ($product->variants()->exists()) {
                        throw ValidationException::withMessages([
                            'csv_file' => "Line {$row['line']}: Product SKU \"{$row['sku']}\" uses variants; import using specific variant SKU instead.",
                        ]);
                    }

                    $this->inventoryReservation->adjustStock(
                        product: $product,
                        variant: null,
                        targetQuantity: $row['stock_quantity'],
                        actor: $actor,
                        reference: $row['reason'],
                    );
                    $updatedCount++;
                }

                $this->auditLogger->record(
                    event: $scopedStore instanceof Store
                        ? SecurityAuditEvent::SellerInventoryBulkImported
                        : SecurityAuditEvent::InventoryBulkImported,
                    user: $scopedStore instanceof Store ? $actor : null,
                    actor: $actor,
                    metadata: array_filter([
                        'store_id' => $scopedStore?->id,
                        'rows_processed' => count($rows),
                        'skus_updated' => $updatedCount,
                    ], static fn (mixed $value): bool => $value !== null),
                    request: $request,
                );

                return [
                    'updatedCount' => $updatedCount,
                    'rowCount' => count($rows),
                ];
            });
        } finally {
            fclose($handle);
        }
    }

    private function hasSpreadsheetFormulaPrefix(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $firstChar = $value[0];

        return in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true);
    }
}
