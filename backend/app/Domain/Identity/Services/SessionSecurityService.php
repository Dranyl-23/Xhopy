<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SessionSecurityService
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    /**
     * Return active device/browser sessions for the authenticated user.
     *
     * @return list<array{
     *     id: string,
     *     ip_address: string,
     *     device: string,
     *     is_current: bool,
     *     last_active_diff: string,
     * }>
     */
    public function listActiveSessions(User $user, string $currentSessionId, ?Request $request = null): array
    {
        $table = (string) config('session.table', 'sessions');
        $rows = DB::table($table)
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        $sessions = [];

        foreach ($rows as $row) {
            $lastActivity = CarbonImmutable::createFromTimestamp((int) $row->last_activity);
            $sessionId = (string) $row->id;

            $sessions[] = [
                'id' => $sessionId,
                'ip_address' => self::displayIp(is_string($row->ip_address) ? $row->ip_address : null),
                'device' => self::summarizeUserAgent(is_string($row->user_agent) ? $row->user_agent : null),
                'is_current' => $sessionId === $currentSessionId,
                'last_active_diff' => $lastActivity->diffForHumans(),
            ];
        }

        if ($sessions === []) {
            $sessions[] = [
                'id' => $currentSessionId !== '' ? $currentSessionId : 'current',
                'ip_address' => self::displayIp($request?->ip()),
                'device' => self::summarizeUserAgent($request?->userAgent()),
                'is_current' => true,
                'last_active_diff' => Date::now()->diffForHumans(),
            ];
        }

        return $sessions;
    }

    /**
     * Revoke a single non-current session belonging strictly to the user.
     */
    public function revokeSession(User $user, string $sessionId, string $currentSessionId, ?Request $request = null): bool
    {
        if ($sessionId === '' || $sessionId === $currentSessionId) {
            return false;
        }

        $table = (string) config('session.table', 'sessions');
        $deleted = DB::table($table)
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            $this->auditLogger->record(
                event: SecurityAuditEvent::SessionRevoked,
                user: $user,
                actor: $user,
                metadata: ['revoked_session_prefix' => substr($sessionId, 0, 8)],
                request: $request,
            );

            return true;
        }

        return false;
    }

    /**
     * Revoke all active sessions except the current one and rotate the remember token.
     */
    public function revokeOtherSessions(User $user, string $currentSessionId, ?Request $request = null): int
    {
        $table = (string) config('session.table', 'sessions');
        $deleted = DB::table($table)
            ->where('user_id', $user->id)
            ->when($currentSessionId !== '', fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->delete();

        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->auditLogger->record(
            event: SecurityAuditEvent::OtherSessionsRevoked,
            user: $user,
            actor: $user,
            metadata: ['revoked_count' => $deleted],
            request: $request,
        );

        return $deleted;
    }

    /**
     * Clear every stored session row for a user (used on password reset or privilege downgrade).
     */
    public function revokeAllSessions(User $user): int
    {
        $table = (string) config('session.table', 'sessions');
        $deleted = DB::table($table)
            ->where('user_id', $user->id)
            ->delete();

        $user->setRememberToken(Str::random(60));
        $user->save();

        return $deleted;
    }

    public static function summarizeUserAgent(?string $userAgent): string
    {
        $agent = trim((string) $userAgent);
        if ($agent === '') {
            return 'Unknown device';
        }

        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') || str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Device',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'Chromium/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        return $os.' · '.$browser;
    }

    private static function displayIp(?string $ip): string
    {
        $clean = trim((string) $ip);

        return $clean !== '' ? $clean : '127.0.0.1';
    }
}
