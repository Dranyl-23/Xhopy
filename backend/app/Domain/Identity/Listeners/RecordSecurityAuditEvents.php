<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Identity\Services\SessionSecurityService;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

final class RecordSecurityAuditEvents
{
    public function __construct(
        private readonly SecurityAuditLogger $auditLogger,
        private readonly SessionSecurityService $sessionSecurity,
    ) {}

    public function handleRegistered(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->record(SecurityAuditEvent::Registered, $event->user, $event->user);
        }
    }

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->record(
                event: SecurityAuditEvent::Login,
                user: $event->user,
                actor: $event->user,
                metadata: [
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ],
            );
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->auditLogger->record(
            event: SecurityAuditEvent::LoginFailed,
            user: $user,
            actor: $user,
            metadata: ['guard' => $event->guard],
        );
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->record(
                event: SecurityAuditEvent::Logout,
                user: $event->user,
                actor: $event->user,
                metadata: ['guard' => $event->guard],
            );
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->sessionSecurity->revokeAllSessions($event->user);

        $this->auditLogger->record(
            event: SecurityAuditEvent::PasswordReset,
            user: $event->user,
            actor: $event->user,
        );
    }

    public function handleVerified(Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->auditLogger->record(SecurityAuditEvent::EmailVerified, $event->user, $event->user);
        }
    }

    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->auditLogger->record(SecurityAuditEvent::TwoFactorEnabled, $event->user, $event->user);
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->auditLogger->record(SecurityAuditEvent::TwoFactorConfirmed, $event->user, $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->auditLogger->record(SecurityAuditEvent::TwoFactorDisabled, $event->user, $event->user);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Registered::class, [self::class, 'handleRegistered']);
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Failed::class, [self::class, 'handleFailed']);
        $events->listen(Logout::class, [self::class, 'handleLogout']);
        $events->listen(PasswordReset::class, [self::class, 'handlePasswordReset']);
        $events->listen(Verified::class, [self::class, 'handleVerified']);
        $events->listen(TwoFactorAuthenticationEnabled::class, [self::class, 'handleTwoFactorEnabled']);
        $events->listen(TwoFactorAuthenticationConfirmed::class, [self::class, 'handleTwoFactorConfirmed']);
        $events->listen(TwoFactorAuthenticationDisabled::class, [self::class, 'handleTwoFactorDisabled']);
    }
}
