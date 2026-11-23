<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Orders\Actions\TransitionSellerOrderStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Seller\Services\SellerWorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\TransitionSellerOrderRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(
        private readonly SellerWorkspaceService $workspaceService,
    ) {}

    public function index(Request $request): Response
    {
        ['store' => $store] = $this->workspaceService->requireApprovedWorkspace($request->user());

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $statusFilter = isset($validated['status']) ? OrderStatus::tryFrom((string) $validated['status']) : null;

        $query = SellerOrder::query()
            ->where('store_id', $store->id)
            ->with(['order:id,order_number,payment_status,shipping_recipient_name,shipping_city,shipping_province,placed_at'])
            ->withCount('items')
            ->orderByDesc('id');

        if ($statusFilter instanceof OrderStatus) {
            $query->where('status', $statusFilter->value);
        }

        if ($search !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $term = "%{$escaped}%";
            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw("LOWER(seller_order_number) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('order', static function (Builder $orderQuery) use ($term): void {
                        $orderQuery->whereRaw("LOWER(order_number) LIKE ? ESCAPE '!'", [$term])
                            ->orWhereRaw("LOWER(shipping_recipient_name) LIKE ? ESCAPE '!'", [$term]);
                    });
            });
        }

        $orders = $query->paginate(20)->withQueryString()->through(static function (SellerOrder $sellerOrder): array {
            $parentOrder = $sellerOrder->order;
            $nextTransition = $sellerOrder->status->allowedSellerTransitions()[0] ?? null;

            return [
                'id' => $sellerOrder->id,
                'seller_order_number' => $sellerOrder->seller_order_number,
                'parent_order_number' => $parentOrder->order_number,
                'status' => $sellerOrder->status->value,
                'status_label' => $sellerOrder->status->label(),
                'payment_status' => $parentOrder->payment_status->value,
                'payment_status_label' => $parentOrder->payment_status->label(),
                'recipient_name' => $parentOrder->shipping_recipient_name,
                'destination_city' => "{$parentOrder->shipping_city}, {$parentOrder->shipping_province}",
                'items_count' => (int) ($sellerOrder->getAttribute('items_count') ?? 0),
                'total_formatted' => MoneyFormatter::format($sellerOrder->total_amount, $sellerOrder->currency),
                'shipping_carrier' => $sellerOrder->shipping_carrier,
                'tracking_number' => $sellerOrder->tracking_number,
                'placed_at_diff' => $parentOrder->placed_at->diffForHumans(),
                'next_seller_status' => $nextTransition?->value,
                'next_seller_status_label' => $nextTransition?->label(),
            ];
        });

        $confirmedStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $confirmedRevenue = (string) (SellerOrder::query()
            ->where('store_id', $store->id)
            ->whereIn('status', $confirmedStatuses)
            ->sum('total_amount') ?: '0.00');

        $stats = [
            'totalOrders' => SellerOrder::query()->where('store_id', $store->id)->count(),
            'toFulfillCount' => SellerOrder::query()
                ->where('store_id', $store->id)
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])
                ->count(),
            'shippedCount' => SellerOrder::query()
                ->where('store_id', $store->id)
                ->where('status', OrderStatus::Shipped->value)
                ->count(),
            'deliveredCount' => SellerOrder::query()
                ->where('store_id', $store->id)
                ->where('status', OrderStatus::Delivered->value)
                ->count(),
            'confirmedRevenueFormatted' => MoneyFormatter::format($confirmedRevenue, 'PHP'),
        ];

        $statuses = array_map(
            static fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            OrderStatus::cases(),
        );

        return Inertia::render('seller/orders/Index', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
            ],
            'sellerOrders' => $orders,
            'statuses' => $statuses,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter instanceof OrderStatus ? $statusFilter->value : '',
            ],
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, SellerOrder $sellerOrder): Response
    {
        $this->workspaceService->requireApprovedWorkspace($request->user());
        Gate::authorize('viewOwned', $sellerOrder);

        $sellerOrder->load([
            'order',
            'items',
            'statusHistory.actor:id,name',
        ]);

        $parentOrder = $sellerOrder->order;
        $allowedTransitions = array_map(
            static fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            $sellerOrder->status->allowedSellerTransitions(),
        );

        return Inertia::render('seller/orders/Show', [
            'sellerOrder' => [
                'id' => $sellerOrder->id,
                'seller_order_number' => $sellerOrder->seller_order_number,
                'parent_order_number' => $parentOrder->order_number,
                'seller_name' => $sellerOrder->seller_name,
                'is_official_store' => $sellerOrder->is_official_store,
                'status' => $sellerOrder->status->value,
                'status_label' => $sellerOrder->status->label(),
                'payment_status' => $parentOrder->payment_status->value,
                'payment_status_label' => $parentOrder->payment_status->label(),
                'subtotal_formatted' => MoneyFormatter::format($sellerOrder->subtotal_amount, $sellerOrder->currency),
                'shipping_formatted' => MoneyFormatter::format($sellerOrder->shipping_amount, $sellerOrder->currency),
                'total_formatted' => MoneyFormatter::format($sellerOrder->total_amount, $sellerOrder->currency),
                'shipping_recipient_name' => $parentOrder->shipping_recipient_name,
                'shipping_phone' => $parentOrder->shipping_phone,
                'shipping_address' => $parentOrder->formattedShippingAddress(),
                'shipping_delivery_notes' => $parentOrder->shipping_delivery_notes,
                'order_notes' => $parentOrder->notes,
                'shipping_carrier' => $sellerOrder->shipping_carrier,
                'tracking_number' => $sellerOrder->tracking_number,
                'placed_at_diff' => $parentOrder->placed_at->diffForHumans(),
                'paid_at_diff' => $sellerOrder->paid_at?->diffForHumans(),
                'shipped_at_diff' => $sellerOrder->shipped_at?->diffForHumans(),
                'delivered_at_diff' => $sellerOrder->delivered_at?->diffForHumans(),
                'cancelled_at_diff' => $sellerOrder->cancelled_at?->diffForHumans(),
                'refunded_at_diff' => $sellerOrder->refunded_at?->diffForHumans(),
                'allowed_transitions' => $allowedTransitions,
                'items' => $sellerOrder->items->map(static fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_title' => $item->variant_title,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => MoneyFormatter::format($item->unit_price, $item->currency),
                    'line_total_formatted' => MoneyFormatter::format($item->line_total, $item->currency),
                ])->values()->all(),
                'status_history' => $sellerOrder->statusHistory->map(static fn (OrderStatusHistory $entry): array => [
                    'id' => $entry->id,
                    'to_status_label' => $entry->to_status->label(),
                    'actor_name' => $entry->actor !== null ? $entry->actor->name : 'System / Webhook',
                    'reason' => $entry->reason,
                    'created_at_diff' => $entry->created_at->diffForHumans(),
                ])->values()->all(),
            ],
        ]);
    }

    public function updateStatus(
        TransitionSellerOrderRequest $request,
        SellerOrder $sellerOrder,
        TransitionSellerOrderStatusAction $transition,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $this->workspaceService->requireApprovedWorkspace($request->user());
        Gate::authorize('fulfillOwned', $sellerOrder);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $nextStatus = OrderStatus::from((string) $request->validated('status'));

        if (! $sellerOrder->status->canSellerTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => "Suborder {$sellerOrder->seller_order_number} cannot transition from {$sellerOrder->status->label()} to {$nextStatus->label()}.",
            ]);
        }

        $previousStatus = $sellerOrder->status;
        $reasonInput = is_string($request->validated('reason')) ? trim((string) $request->validated('reason')) : '';
        $reason = $reasonInput !== '' ? $reasonInput : "Updated to {$nextStatus->label()} by {$actor->name}.";

        try {
            $updated = $transition->execute(
                sellerOrder: $sellerOrder,
                nextStatus: $nextStatus,
                actor: $actor,
                reason: $reason,
                shippingCarrier: is_string($request->validated('shipping_carrier'))
                    ? (string) $request->validated('shipping_carrier')
                    : null,
                trackingNumber: is_string($request->validated('tracking_number'))
                    ? (string) $request->validated('tracking_number')
                    : null,
                asSeller: true,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'status' => $exception->getMessage(),
            ]);
        }

        $updated->loadMissing('order.user');

        $auditLogger->record(
            event: SecurityAuditEvent::SellerOrderStatusUpdated,
            user: $updated->order->user,
            actor: $actor,
            metadata: [
                'order_id' => $updated->order_id,
                'seller_order_id' => $updated->id,
                'seller_order_number' => $updated->seller_order_number,
                'store_id' => $updated->store_id,
                'from_status' => $previousStatus->value,
                'to_status' => $updated->status->value,
                'reason' => $reason,
            ],
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("Suborder {$updated->seller_order_number} updated to {$updated->status->label()}."),
        ]);

        return back();
    }
}
