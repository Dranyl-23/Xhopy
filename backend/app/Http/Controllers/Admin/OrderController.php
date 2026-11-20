<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkTransitionAdminOrdersRequest;
use App\Http\Requests\Admin\TransitionAdminOrderRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $statusFilter = isset($validated['status']) ? OrderStatus::tryFrom((string) $validated['status']) : null;

        $query = Order::query()
            ->with(['user:id,name,email'])
            ->withCount('items')
            ->orderByDesc('id');

        if ($statusFilter instanceof OrderStatus) {
            $query->where('status', $statusFilter->value);
        }

        if ($search !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $term = "%{$escaped}%";
            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw("LOWER(order_number) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(shipping_recipient_name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('user', static function (Builder $userQuery) use ($term): void {
                        $userQuery->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                            ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$term]);
                    });
            });
        }

        $orders = $query->paginate(20)->withQueryString()->through(static function (Order $order): array {
            $customer = $order->user;

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'items_count' => (int) ($order->getAttribute('items_count') ?? 0),
                'total_formatted' => MoneyFormatter::format($order->total_amount, $order->currency),
                'placed_at_diff' => $order->placed_at->diffForHumans(),
                'can_advance_to_processing' => $order->status === OrderStatus::Paid,
            ];
        });

        $statuses = array_map(
            static fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            OrderStatus::cases(),
        );

        return Inertia::render('admin/orders/Index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'filters' => [
                'q' => $search,
                'status' => $statusFilter instanceof OrderStatus ? $statusFilter->value : '',
            ],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('manage', Order::class);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $order->load(['user:id,name,email,phone', 'items', 'statusHistory.actor:id,name', 'payments']);

        $allowedTransitions = array_map(
            static fn (OrderStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            $order->status->allowedAdminTransitions($actor->isAdmin()),
        );

        return Inertia::render('admin/orders/Show', [
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
                'customer' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ],
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
                'cancelled_at_diff' => $order->cancelled_at?->diffForHumans(),
                'refunded_at_diff' => $order->refunded_at?->diffForHumans(),
                'allowed_transitions' => $allowedTransitions,
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
                'payments' => $order->payments->map(static fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'provider' => $payment->provider,
                    'provider_reference' => $payment->provider_reference,
                    'status_label' => $payment->status->label(),
                    'amount_formatted' => MoneyFormatter::format($payment->amount, $payment->currency),
                    'payment_method_type' => $payment->payment_method_type,
                    'paid_at_diff' => $payment->paid_at?->diffForHumans(),
                ])->values()->all(),
                'status_history' => $order->statusHistory->map(static fn (OrderStatusHistory $entry): array => [
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
        TransitionAdminOrderRequest $request,
        Order $order,
        TransitionOrderStatusAction $transition,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $nextStatus = OrderStatus::from((string) $request->validated('status'));

        if ($nextStatus === OrderStatus::Refunded) {
            Gate::authorize('refund', $order);
        }

        if (! $order->status->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages([
                'status' => "Order {$order->order_number} cannot transition from {$order->status->label()} to {$nextStatus->label()}.",
            ]);
        }

        $previousStatus = $order->status;
        $reasonInput = is_string($request->validated('reason')) ? trim((string) $request->validated('reason')) : '';
        $reason = $reasonInput !== '' ? $reasonInput : "Updated to {$nextStatus->label()} by {$actor->name}.";

        $updated = $transition->execute(
            order: $order,
            nextStatus: $nextStatus,
            actor: $actor,
            reason: $reason,
            shippingCarrier: is_string($request->validated('shipping_carrier'))
                ? (string) $request->validated('shipping_carrier')
                : null,
            trackingNumber: is_string($request->validated('tracking_number'))
                ? (string) $request->validated('tracking_number')
                : null,
        );

        $auditLogger->record(
            event: SecurityAuditEvent::OrderStatusUpdated,
            user: $updated->user,
            actor: $actor,
            metadata: [
                'order_id' => $updated->id,
                'order_number' => $updated->order_number,
                'from_status' => $previousStatus->value,
                'to_status' => $updated->status->value,
                'reason' => $reason,
            ],
            request: $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("Order {$updated->order_number} updated to {$updated->status->label()}."),
        ]);

        return back();
    }

    public function bulkTransition(
        BulkTransitionAdminOrdersRequest $request,
        TransitionOrderStatusAction $transition,
        SecurityAuditLogger $auditLogger,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $targetStatus = OrderStatus::from((string) $request->validated('status'));
        $rawReason = is_string($request->validated('reason')) ? trim((string) $request->validated('reason')) : '';
        $reason = $rawReason !== '' ? $rawReason : "Bulk updated to {$targetStatus->label()} by {$actor->name}.";

        /** @var list<int> $orderIds */
        $orderIds = array_values(array_unique(array_map('intval', (array) $request->validated('order_ids'))));

        $orders = Order::query()->whereIn('id', $orderIds)->orderBy('id')->get();
        $updatedNumbers = [];
        $skipped = 0;

        foreach ($orders as $order) {
            if (! $order->status->canTransitionTo($targetStatus)) {
                $skipped++;

                continue;
            }

            $updated = $transition->execute(
                order: $order,
                nextStatus: $targetStatus,
                actor: $actor,
                reason: $reason,
            );

            $updatedNumbers[] = $updated->order_number;
        }

        if ($updatedNumbers !== []) {
            $auditLogger->record(
                event: SecurityAuditEvent::OrdersBulkTransitioned,
                user: null,
                actor: $actor,
                metadata: [
                    'to_status' => $targetStatus->value,
                    'updated_count' => count($updatedNumbers),
                    'skipped_count' => $skipped,
                    'order_numbers' => array_slice($updatedNumbers, 0, 25),
                ],
                request: $request,
            );
        }

        $count = count($updatedNumbers);
        $message = $skipped > 0
            ? "Updated {$count} order(s) to {$targetStatus->label()} ({$skipped} skipped due to state guard)."
            : "Updated {$count} order(s) to {$targetStatus->label()}.";

        Inertia::flash('toast', [
            'type' => $count > 0 ? 'success' : 'error',
            'message' => $message,
        ]);

        return back();
    }
}
