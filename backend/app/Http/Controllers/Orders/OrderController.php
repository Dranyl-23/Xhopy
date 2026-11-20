<?php

namespace App\Http\Controllers\Orders;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Actions\CreateCheckoutPaymentAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $orders = $user->orders()
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(12)
            ->through(static fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'items_count' => (int) ($order->getAttribute('items_count') ?? 0),
                'total_formatted' => MoneyFormatter::format($order->total_amount, $order->currency),
                'placed_at_diff' => $order->placed_at->diffForHumans(),
            ]);

        $notifications = $user->notifications()
            ->latest()
            ->limit(6)
            ->get()
            ->map(static fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => (string) ($notification->data['title'] ?? 'Order update'),
                'message' => (string) ($notification->data['message'] ?? ''),
                'action_url' => is_string($notification->data['action_url'] ?? null) ? $notification->data['action_url'] : null,
                'is_unread' => $notification->read_at === null,
                'created_at_diff' => $notification->created_at?->diffForHumans() ?? '',
            ])
            ->values()
            ->all();

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'notifications' => $notifications,
            'unreadNotificationsCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $user->unreadNotifications()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notifications marked as read.')]);

        return back();
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'statusHistory', 'latestPayment']);
        $latestPayment = $order->latestPayment;

        return Inertia::render('orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'subtotal_formatted' => MoneyFormatter::format($order->subtotal_amount, $order->currency),
                'shipping_formatted' => MoneyFormatter::format($order->shipping_amount, $order->currency),
                'total_formatted' => MoneyFormatter::format($order->total_amount, $order->currency),
                'shipping_recipient_name' => $order->shipping_recipient_name,
                'shipping_phone' => $order->shipping_phone,
                'shipping_address' => $order->formattedShippingAddress(),
                'shipping_delivery_notes' => $order->shipping_delivery_notes,
                'notes' => $order->notes,
                'shipping_carrier' => $order->shipping_carrier,
                'tracking_number' => $order->tracking_number,
                'placed_at_diff' => $order->placed_at->diffForHumans(),
                'paid_at_diff' => $order->paid_at?->diffForHumans(),
                'shipped_at_diff' => $order->shipped_at?->diffForHumans(),
                'delivered_at_diff' => $order->delivered_at?->diffForHumans(),
                'refunded_at_diff' => $order->refunded_at?->diffForHumans(),
                'can_cancel' => Gate::allows('cancel', $order),
                'can_retry_payment' => $order->status === OrderStatus::PendingPayment,
                'payment_method_type' => $latestPayment?->payment_method_type,
                'items' => $order->items->map(static fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_title' => $item->variant_title,
                    'sku' => $item->sku,
                    'seller_name' => $item->seller_name,
                    'quantity' => $item->quantity,
                    'unit_price_formatted' => MoneyFormatter::format($item->unit_price, $item->currency),
                    'line_total_formatted' => MoneyFormatter::format($item->line_total, $item->currency),
                ])->values()->all(),
                'status_history' => $order->statusHistory->map(static fn (OrderStatusHistory $entry): array => [
                    'id' => $entry->id,
                    'to_status_label' => $entry->to_status->label(),
                    'reason' => $entry->reason,
                    'created_at_diff' => $entry->created_at->diffForHumans(),
                ])->values()->all(),
            ],
        ]);
    }

    public function cancel(Request $request, Order $order, TransitionOrderStatusAction $transition): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $transition->execute(
            order: $order,
            nextStatus: OrderStatus::Cancelled,
            actor: $user,
            reason: 'Cancelled by customer prior to payment confirmation.',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled and reserved stock released.')]);

        return back();
    }

    public function retryPayment(Order $order, CreateCheckoutPaymentAction $createPayment): SymfonyResponse
    {
        Gate::authorize('view', $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return redirect()->route('orders.show', ['order' => $order->id]);
        }

        $payment = $createPayment->execute($order);
        $checkoutUrl = (string) $payment->checkout_url;

        if ($checkoutUrl !== '' && str_starts_with($checkoutUrl, 'https://')) {
            return Inertia::location($checkoutUrl);
        }

        return redirect()->to($checkoutUrl !== '' ? $checkoutUrl : route('orders.show', ['order' => $order->id]));
    }
}
