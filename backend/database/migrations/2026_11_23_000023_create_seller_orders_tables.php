<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('seller_order_number', 40)->unique();
            $table->string('seller_name', 150);
            $table->boolean('is_official_store')->default(false);
            $table->string('status', 32)->index();
            $table->char('currency', 3)->default('PHP');
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('shipping_amount', 12, 2)->default('0.00');
            $table->decimal('discount_amount', 12, 2)->default('0.00');
            $table->decimal('tax_amount', 12, 2)->default('0.00');
            $table->decimal('total_amount', 12, 2);
            $table->string('shipping_carrier', 80)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'store_id']);
            $table->index(['store_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('seller_order_id')
                ->nullable()
                ->after('order_id')
                ->constrained('seller_orders')
                ->nullOnDelete();
        });

        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->foreignId('seller_order_id')
                ->nullable()
                ->after('order_id')
                ->constrained('seller_orders')
                ->cascadeOnDelete();
        });

        // Backfill existing pre-MV-5 orders into seller_orders so historical orders remain consistent.
        $fallbackStoreId = DB::table('stores')->where('slug', 'xhopy-official')->value('id');
        if ($fallbackStoreId === null) {
            $fallbackStoreId = DB::table('stores')->orderBy('id')->value('id');
        }

        if ($fallbackStoreId !== null) {
            $orders = DB::table('orders')->orderBy('id')->get();
            $storesById = DB::table('stores')->get()->keyBy('id');

            foreach ($orders as $order) {
                $items = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->get();

                if ($items->isEmpty()) {
                    continue;
                }

                $grouped = [];
                foreach ($items as $item) {
                    $resolvedStoreId = $item->store_id !== null ? (int) $item->store_id : (int) $fallbackStoreId;
                    $grouped[$resolvedStoreId][] = $item;
                }

                $sequence = 1;
                foreach ($grouped as $storeId => $storeItems) {
                    $storeRow = $storesById->get($storeId);
                    $storeName = $storeRow !== null
                        ? (string) $storeRow->name
                        : ((string) ($storeItems[0]->seller_name ?: 'XHopy Official'));
                    $isOfficial = $storeRow !== null && (bool) $storeRow->is_official;

                    $groupCentavos = 0;
                    foreach ($storeItems as $storeItem) {
                        $groupCentavos += (int) round(((float) $storeItem->line_total) * 100);
                    }
                    $amountDecimal = number_format($groupCentavos / 100, 2, '.', '');

                    $sellerOrderId = DB::table('seller_orders')->insertGetId([
                        'order_id' => $order->id,
                        'store_id' => $storeId,
                        'seller_order_number' => "{$order->order_number}-S{$sequence}",
                        'seller_name' => $storeName,
                        'is_official_store' => $isOfficial,
                        'status' => $order->status,
                        'currency' => $order->currency,
                        'subtotal_amount' => $amountDecimal,
                        'shipping_amount' => '0.00',
                        'discount_amount' => '0.00',
                        'tax_amount' => '0.00',
                        'total_amount' => $amountDecimal,
                        'shipping_carrier' => $order->shipping_carrier,
                        'tracking_number' => $order->tracking_number,
                        'paid_at' => $order->paid_at,
                        'shipped_at' => $order->shipped_at,
                        'delivered_at' => $order->delivered_at,
                        'cancelled_at' => $order->cancelled_at,
                        'refunded_at' => $order->refunded_at,
                        'created_at' => $order->created_at ?? now(),
                        'updated_at' => $order->updated_at ?? now(),
                    ]);

                    $itemIds = array_map(static fn (object $row): int => (int) $row->id, $storeItems);
                    DB::table('order_items')
                        ->whereIn('id', $itemIds)
                        ->update([
                            'store_id' => $storeId,
                            'seller_order_id' => $sellerOrderId,
                        ]);

                    $sequence++;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seller_order_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seller_order_id');
        });

        Schema::dropIfExists('seller_orders');
    }
};
