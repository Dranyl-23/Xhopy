<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Identity\Services\SessionSecurityService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateUserRoleAction
{
    public function __construct(
        private readonly SessionSecurityService $sessionSecurity,
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    public function execute(User $actor, User $target, UserRole $newRole, ?Request $request = null): User
    {
        if (! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only administrators can modify account roles.');
        }

        if ($actor->id === $target->id && $newRole !== UserRole::Admin) {
            throw new InvalidArgumentException('You cannot remove your own administrator role.');
        }

        $previousRole = $target->role;

        if ($previousRole === $newRole) {
            return $target;
        }

        DB::transaction(function () use ($actor, $target, $previousRole, $newRole, $request): void {
            $target->forceFill(['role' => $newRole])->save();

            // Revoke active sessions when privilege changes so permissions refresh cleanly.
            $this->sessionSecurity->revokeAllSessions($target);

            $this->auditLogger->record(
                event: SecurityAuditEvent::RoleUpdated,
                user: $target,
                actor: $actor,
                metadata: [
                    'old_role' => $previousRole->value,
                    'new_role' => $newRole->value,
                ],
                request: $request,
            );
        });

        return $target->refresh();
    }
}
