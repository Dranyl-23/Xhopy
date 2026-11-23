<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\StaffPermission;
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

    /**
     * @param  list<string>|null  $staffPermissions
     */
    public function execute(
        User $actor,
        User $target,
        UserRole $newRole,
        ?array $staffPermissions = null,
        bool $updatePermissions = false,
        ?Request $request = null,
    ): User {
        if (! $actor->isAdmin()) {
            throw new InvalidArgumentException('Only administrators can modify account roles.');
        }

        if ($actor->id === $target->id && $newRole !== UserRole::Admin) {
            throw new InvalidArgumentException('You cannot remove your own administrator role.');
        }

        $previousRole = $target->role;
        $previousPermissions = $target->staff_permissions;

        $nextPermissions = $newRole === UserRole::Staff
            ? ($updatePermissions ? self::normalizeStaffPermissions($staffPermissions) : $previousPermissions)
            : null;

        $roleChanged = $previousRole !== $newRole;
        $permissionsChanged = $newRole === UserRole::Staff
            ? ($updatePermissions && $previousPermissions !== $nextPermissions)
            : ($previousPermissions !== null);

        if (! $roleChanged && ! $permissionsChanged) {
            return $target;
        }

        DB::transaction(function () use (
            $actor,
            $target,
            $previousRole,
            $newRole,
            $previousPermissions,
            $nextPermissions,
            $roleChanged,
            $permissionsChanged,
            $request,
        ): void {
            $target->forceFill([
                'role' => $newRole,
                'staff_permissions' => $nextPermissions,
            ])->save();

            // Revoke active sessions when privilege changes so permissions refresh cleanly.
            $this->sessionSecurity->revokeAllSessions($target);

            if ($roleChanged) {
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
            }

            if ($permissionsChanged) {
                $this->auditLogger->record(
                    event: SecurityAuditEvent::StaffPermissionsUpdated,
                    user: $target,
                    actor: $actor,
                    metadata: [
                        'role' => $newRole->value,
                        'old_permissions' => $previousPermissions,
                        'new_permissions' => $nextPermissions,
                    ],
                    request: $request,
                );
            }
        });

        return $target->refresh();
    }

    /**
     * @param  list<string>|null  $staffPermissions
     * @return list<string>|null
     */
    private static function normalizeStaffPermissions(?array $staffPermissions): ?array
    {
        if ($staffPermissions === null) {
            return null;
        }

        $valid = [];
        foreach ($staffPermissions as $permission) {
            $resolved = StaffPermission::tryFrom($permission);
            if ($resolved instanceof StaffPermission && ! in_array($resolved->value, $valid, true)) {
                $valid[] = $resolved->value;
            }
        }

        return $valid;
    }
}
