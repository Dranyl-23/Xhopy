<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivilegedMfa
{
    /**
     * When strict privileged MFA is configured, require staff/admin users to configure 2FA or a Passkey.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('fortify.require_mfa_for_privileged', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->canAccessAdminPanel() || $user->hasMfaEnabled()) {
            return $next($request);
        }

        if (! $request->isMethodSafe() || $request->expectsJson()) {
            abort(403, 'Multi-factor authentication (2FA or Passkey) is required for staff and administrator actions.');
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Enable two-factor authentication or a passkey to access administrative workspaces.'),
        ]);

        return redirect()->route('security.edit');
    }
}
