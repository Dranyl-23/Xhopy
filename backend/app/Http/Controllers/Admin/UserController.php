<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Identity\Actions\UpdateUserRoleAction;
use App\Domain\Identity\Enums\StaffPermission;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', Rule::enum(UserRole::class)],
        ]);

        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $roleFilter = isset($validated['role']) ? (string) $validated['role'] : '';

        $usersQuery = User::query()
            ->withExists(['passkeys'])
            ->withCount('orders')
            ->withSum(['orders as paid_total_sum' => static function ($query): void {
                $query->whereIn('status', ['paid', 'processing', 'shipped', 'delivered']);
            }], 'total_amount')
            ->orderBy('id');

        if ($search !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $usersQuery->where(function ($query) use ($escaped): void {
                $query->where('name', 'like', '%'.$escaped.'%')
                    ->orWhere('email', 'like', '%'.$escaped.'%');
            });
        }

        if ($roleFilter !== '') {
            $usersQuery->where('role', $roleFilter);
        }

        $users = $usersQuery->paginate(20)->withQueryString()->through(static fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'staff_permissions' => $user->resolvedStaffPermissions(),
            'email_verified' => $user->hasVerifiedEmail(),
            'has_two_factor' => $user->hasEnabledTwoFactorAuthentication(),
            'has_passkeys' => (bool) ($user->getAttribute('passkeys_exists') ?? false),
            'orders_count' => (int) ($user->getAttribute('orders_count') ?? 0),
            'total_spent_formatted' => MoneyFormatter::format((string) ($user->getAttribute('paid_total_sum') ?? '0.00'), 'PHP'),
            'created_at_diff' => $user->created_at?->diffForHumans() ?? '',
        ]);

        $recentAuditLogs = SecurityAuditLog::query()
            ->with(['user:id,name,email', 'actor:id,name,email'])
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(static function (SecurityAuditLog $log): array {
                $targetUser = $log->user;
                $actorUser = $log->actor;

                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'label' => $log->eventLabel(),
                    'user_name' => $targetUser !== null ? $targetUser->name : 'System',
                    'user_email' => $targetUser !== null ? $targetUser->email : '',
                    'actor_name' => $actorUser !== null ? $actorUser->name : 'System',
                    'ip_address' => $log->ip_address ?? '—',
                    'metadata' => $log->metadata,
                    'created_at_diff' => $log->created_at->diffForHumans(),
                ];
            })
            ->values()
            ->all();

        $roles = array_map(
            static fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            UserRole::cases(),
        );

        $staffPermissions = array_map(
            static fn (StaffPermission $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
            ],
            StaffPermission::cases(),
        );

        return Inertia::render('admin/Users', [
            'users' => $users,
            'recentAuditLogs' => $recentAuditLogs,
            'roles' => $roles,
            'staffPermissions' => $staffPermissions,
            'filters' => [
                'q' => $search,
                'role' => $roleFilter,
            ],
        ]);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        UpdateUserRoleAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $validated = $request->validated();
        $targetRole = UserRole::from((string) $validated['role']);
        $updatePermissions = array_key_exists('staff_permissions', $validated);
        $rawPermissions = $updatePermissions && is_array($validated['staff_permissions'])
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, $validated['staff_permissions']))
            : null;

        try {
            $action->execute(
                actor: $actor,
                target: $user,
                newRole: $targetRole,
                staffPermissions: $rawPermissions,
                updatePermissions: $updatePermissions,
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'role' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Updated role for :name to :role.', [
                'name' => $user->name,
                'role' => $targetRole->label(),
            ]),
        ]);

        return back();
    }
}
