<?php

namespace App\Domain\Admin\Services;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class AdminAnalyticsService
{
    private const int LOW_STOCK_THRESHOLD = 5;

    /**
     * Build server-verified operational KPIs, 7-day revenue trend, top products, and low-stock watchlists.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $paidStatusValues = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $totalOrders = Order::query()->count();
        $confirmedOrdersCount = Order::query()->whereIn('status', $paidStatusValues)->count();
        $pendingPaymentCount = Order::query()->where('status', OrderStatus::PendingPayment->value)->count();
        $fulfillmentQueueCount = Order::query()
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])
            ->count();
        $shippedOrDeliveredCount = Order::query()
            ->whereIn('status', [OrderStatus::Shipped->value, OrderStatus::Delivered->value])
            ->count();
        $cancelledOrRefundedCount = Order::query()
            ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Expired->value, OrderStatus::Refunded->value])
            ->count();

        $confirmedRevenue = (string) (Order::query()->whereIn('status', $paidStatusValues)->sum('total_amount') ?: '0.00');
        $confirmedRevenueCentavos = MoneyFormatter::toCentavos($confirmedRevenue);
        $avgOrderCentavos = $confirmedOrdersCount > 0 ? (int) round($confirmedRevenueCentavos / $confirmedOrdersCount) : 0;

        $activeProducts = Product::query()
            ->with('variants')
            ->where('status', ProductStatus::Active->value)
            ->get();

        $lowStockItems = [];
        $outOfStockCount = 0;

        foreach ($activeProducts as $product) {
            $available = $product->totalAvailableStock();
            if ($available <= 0) {
                $outOfStockCount++;
            }

            if ($available <= self::LOW_STOCK_THRESHOLD) {
                $lowStockItems[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $available,
                    'variant_count' => $product->variants->count(),
                ];
            }
        }

        usort($lowStockItems, static fn (array $a, array $b): int => $a['stock_quantity'] <=> $b['stock_quantity']);

        $windowStart = Date::now()->subDays(6)->startOfDay();
        $recentConfirmedOrders = Order::query()
            ->whereIn('status', $paidStatusValues)
            ->where('placed_at', '>=', $windowStart)
            ->get(['placed_at', 'total_amount']);

        $dailyBuckets = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Date::now()->subDays($i);
            $dateKey = $day->format('Y-m-d');
            $dailyBuckets[$dateKey] = [
                'date' => $dateKey,
                'label' => $day->format('M j'),
                'orders' => 0,
                'revenueCentavos' => 0,
            ];
        }

        foreach ($recentConfirmedOrders as $order) {
            $key = $order->placed_at->format('Y-m-d');
            if (isset($dailyBuckets[$key])) {
                $dailyBuckets[$key]['orders']++;
                $dailyBuckets[$key]['revenueCentavos'] += MoneyFormatter::toCentavos($order->total_amount);
            }
        }

        $maxRevenueCentavos = max(1, ...array_column($dailyBuckets, 'revenueCentavos'));

        $dailyTrend = array_map(static fn (array $bucket): array => [
            'date' => $bucket['date'],
            'label' => $bucket['label'],
            'orders' => $bucket['orders'],
            'revenueCentavos' => $bucket['revenueCentavos'],
            'revenueFormatted' => MoneyFormatter::format(number_format($bucket['revenueCentavos'] / 100, 2, '.', ''), 'PHP'),
            'percentOfPeak' => (int) round(($bucket['revenueCentavos'] / $maxRevenueCentavos) * 100),
        ], array_values($dailyBuckets));

        $topSellingProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', $paidStatusValues)
            ->groupBy('order_items.product_name', 'order_items.sku')
            ->select([
                'order_items.product_name',
                'order_items.sku',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.line_total) as gross_sales'),
            ])
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get()
            ->map(static fn ($row): array => [
                'product_name' => (string) $row->getAttribute('product_name'),
                'sku' => (string) $row->getAttribute('sku'),
                'units_sold' => (int) $row->getAttribute('units_sold'),
                'gross_sales_formatted' => MoneyFormatter::format((string) ($row->getAttribute('gross_sales') ?? '0.00'), 'PHP'),
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                'grossRevenueFormatted' => MoneyFormatter::format($confirmedRevenue, 'PHP'),
                'avgOrderValueFormatted' => MoneyFormatter::format(number_format($avgOrderCentavos / 100, 2, '.', ''), 'PHP'),
                'totalOrders' => $totalOrders,
                'confirmedOrdersCount' => $confirmedOrdersCount,
                'pendingPaymentCount' => $pendingPaymentCount,
                'fulfillmentQueueCount' => $fulfillmentQueueCount,
                'shippedOrDeliveredCount' => $shippedOrDeliveredCount,
                'cancelledOrRefundedCount' => $cancelledOrRefundedCount,
                'lowStockCount' => count($lowStockItems),
                'outOfStockCount' => $outOfStockCount,
            ],
            'dailyTrend' => $dailyTrend,
            'topSellingProducts' => $topSellingProducts,
            'lowStockItems' => array_slice($lowStockItems, 0, 8),
        ];
    }
}
