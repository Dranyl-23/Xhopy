<?php

namespace App\Http\Controllers\Account;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Models\SecurityAuditLog;
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
        $unreadNotificationsCount = $user->unreadNotifications()->count();

        /** @var CustomerAddress|null $defaultShipping */
        $defaultShipping = $user->addresses()
            ->where('is_default_shipping', true)
            ->orderByDesc('updated_at')
            ->first();

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
                'unreadNotificationsCount' => $unreadNotificationsCount,
                'defaultShippingAddress' => $defaultShipping ? [
                    'label' => $defaultShipping->label,
                    'recipient_name' => $defaultShipping->recipient_name,
                    'phone' => $defaultShipping->phone,
                    'formatted_line' => $defaultShipping->formattedLine(),
                ] : null,
                'recentSecurityEvents' => $recentEvents,
            ],
        ]);
    }
}
