<?php

namespace App\Http\Controllers\Account;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $addressCount = $user->addresses()->count();
        $orderCount = $user->orders()->count();
        $wishlistCount = $user->wishlistItems()->count();
        $unreadNotificationsCount = $user->unreadNotifications()->count();

        $totalSpentRaw = $user->orders()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->sum('total_amount');
        $totalSpentFormatted = MoneyFormatter::format((string) $totalSpentRaw, 'PHP');

        /** @var CustomerAddress|null $defaultShipping */
        $defaultShipping = $user->addresses()
            ->where('is_default_shipping', true)
            ->orderByDesc('updated_at')
            ->first();

        $recentOrders = $user->orders()
            ->withCount('items')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(static fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'items_count' => (int) ($order->getAttribute('items_count') ?? 0),
                'total_formatted' => MoneyFormatter::format($order->total_amount, $order->currency),
                'placed_at_diff' => $order->placed_at->diffForHumans(),
            ])
            ->values()
            ->all();

        $recentPayments = Payment::query()
            ->where('user_id', $user->id)
            ->with('order:id,order_number')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(static function (Payment $payment): array {
                $order = $payment->order;

                return [
                    'id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'order_number' => $order->order_number,
                    'provider' => ucfirst($payment->provider),
                    'payment_method_type' => $payment->payment_method_type,
                    'status' => $payment->status->value,
                    'status_label' => $payment->status->label(),
                    'amount_formatted' => MoneyFormatter::format($payment->amount, $payment->currency),
                    'activity_at_diff' => ($payment->paid_at ?? $payment->created_at)?->diffForHumans() ?? '',
                ];
            })
            ->values()
            ->all();

        $recentEvents = $user->securityAuditLogs()
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(static fn (SecurityAuditLog $log): array => [
                'id' => $log->id,
                'label' => $log->eventLabel(),
                'ip_address' => $log->ip_address ?? '—',
                'created_at_diff' => $log->created_at->diffForHumans(),
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'accountSummary' => [
                'roleLabel' => $user->role->label(),
                'emailVerified' => $user->hasVerifiedEmail(),
                'hasMfaEnabled' => $user->hasMfaEnabled(),
                'addressCount' => $addressCount,
                'orderCount' => $orderCount,
                'wishlistCount' => $wishlistCount,
                'totalSpentFormatted' => $totalSpentFormatted,
                'unreadNotificationsCount' => $unreadNotificationsCount,
                'defaultShippingAddress' => $defaultShipping ? [
                    'label' => $defaultShipping->label,
                    'recipient_name' => $defaultShipping->recipient_name,
                    'phone' => $defaultShipping->phone,
                    'formatted_line' => $defaultShipping->formattedLine(),
                ] : null,
                'recentOrders' => $recentOrders,
                'recentPayments' => $recentPayments,
                'recentSecurityEvents' => $recentEvents,
            ],
        ]);
    }
}
