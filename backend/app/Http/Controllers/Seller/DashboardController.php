<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $profile = $user->sellerProfile()->with('store')->first();
        $store = $profile instanceof SellerProfile && $profile->store instanceof Store
            ? $profile->store
            : $user->primaryStore();

        if (! $profile instanceof SellerProfile || ! $store instanceof Store) {
            abort(403);
        }

        $totalProducts = Product::query()->where('store_id', $store->id)->count();
        $activeProducts = Product::query()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::Active)
            ->count();
        $pendingReviewProducts = Product::query()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::PendingReview)
            ->count();
        $rejectedProducts = Product::query()
            ->where('store_id', $store->id)
            ->where('status', ProductStatus::Rejected)
            ->count();
        $lowStockProducts = Product::query()
            ->where('store_id', $store->id)
            ->where('stock_quantity', '<=', 5)
            ->count();
        $orderLinesCount = OrderItem::query()->where('store_id', $store->id)->count();
        $sellerOrdersCount = SellerOrder::query()->where('store_id', $store->id)->count();
        $pendingFulfillmentOrders = SellerOrder::query()
            ->where('store_id', $store->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])
            ->count();
        $shippedOrdersCount = SellerOrder::query()
            ->where('store_id', $store->id)
            ->where('status', OrderStatus::Shipped->value)
            ->count();
        $deliveredOrdersCount = SellerOrder::query()
            ->where('store_id', $store->id)
            ->where('status', OrderStatus::Delivered->value)
            ->count();
        $confirmedRevenue = (string) (SellerOrder::query()
            ->where('store_id', $store->id)
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ])
            ->sum('total_amount') ?: '0.00');

        return Inertia::render('seller/Dashboard', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status->value,
                'status_label' => $store->status->label(),
                'contact_email' => $store->contact_email,
                'contact_phone' => $store->contact_phone,
                'description' => $store->description,
            ],
            'profile' => [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'business_type' => $profile->business_type,
                'status' => $profile->status->value,
                'status_label' => $profile->status->label(),
                'approved_at' => $profile->approved_at?->toDayDateTimeString(),
            ],
            'metrics' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'pending_review_products' => $pendingReviewProducts,
                'rejected_products' => $rejectedProducts,
                'low_stock_products' => $lowStockProducts,
                'order_lines_count' => $orderLinesCount,
                'seller_orders_count' => $sellerOrdersCount,
                'pending_fulfillment_orders' => $pendingFulfillmentOrders,
                'shipped_orders' => $shippedOrdersCount,
                'delivered_orders' => $deliveredOrdersCount,
                'confirmed_revenue_formatted' => MoneyFormatter::format($confirmedRevenue, 'PHP'),
            ],
        ]);
    }
}
