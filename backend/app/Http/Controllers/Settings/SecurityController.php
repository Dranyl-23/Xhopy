<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Identity\Services\SessionSecurityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    public function __construct(
        private readonly SessionSecurityService $sessionSecurity,
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'activeSessions' => $this->sessionSecurity->listActiveSessions(
                $user,
                $request->session()->getId(),
                $request,
            ),
            'securityEvents' => $user->securityAuditLogs()
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(static fn (SecurityAuditLog $log): array => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'label' => $log->eventLabel(),
                    'ip_address' => $log->ip_address ?? '—',
                    'device' => SessionSecurityService::summarizeUserAgent($log->user_agent),
                    'created_at_diff' => $log->created_at->diffForHumans(),
                ])
                ->values()
                ->all(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password and revoke other active sessions.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $currentSessionId = $request->session()->getId();

        $user->update([
            'password' => $request->password,
        ]);

        $this->sessionSecurity->revokeOtherSessions($user, $currentSessionId, $request);
        $request->session()->regenerate();

        $this->auditLogger->record(
            event: SecurityAuditEvent::PasswordUpdated,
            user: $user,
            actor: $user,
            request: $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated and other sessions signed out.')]);

        return back();
    }

    /**
     * Sign out a single other active session for the authenticated user.
     */
    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $revoked = $this->sessionSecurity->revokeSession(
            $user,
            $sessionId,
            $request->session()->getId(),
            $request,
        );

        Inertia::flash('toast', [
            'type' => $revoked ? 'success' : 'error',
            'message' => $revoked ? __('Session signed out.') : __('Session could not be signed out.'),
        ]);

        return back();
    }

    /**
     * Sign out all other sessions except the current device.
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->sessionSecurity->revokeOtherSessions(
            $user,
            $request->session()->getId(),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All other active sessions were signed out.')]);

        return back();
    }
}
