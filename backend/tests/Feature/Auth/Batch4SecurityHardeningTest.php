<?php

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('password reset endpoint returns identical non-enumerating status for unknown and existing emails', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'real.buyer@gmail.com']);

    $unknownResponse = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'does-not-exist@gmail.com']);

    $unknownResponse->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors();

    $knownResponse = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'real.buyer@gmail.com']);

    $knownResponse->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors();

    expect($unknownResponse->getSession()->get('status'))
        ->toBe($knownResponse->getSession()->get('status'));

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

test('password update revokes other stored sessions and records security audit logs', function () {
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);

    DB::table('sessions')->insert([
        [
            'id' => 'other-device-session-1',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.5',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/125.0',
            'payload' => 'a:0:{}',
            'last_activity' => time() - 120,
        ],
        [
            'id' => 'unrelated-user-session',
            'user_id' => $user->id + 999,
            'ip_address' => '10.0.0.6',
            'user_agent' => 'Mozilla/5.0',
            'payload' => 'a:0:{}',
            'last_activity' => time() - 120,
        ],
    ]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewStr0ng!Pass2026',
            'password_confirmation' => 'NewStr0ng!Pass2026',
        ])
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'other-device-session-1')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'unrelated-user-session')->exists())->toBeTrue()
        ->and($user->fresh()?->remember_token)->not->toBe('old-remember-token')
        ->and(SecurityAuditLog::query()->where('user_id', $user->id)->where('event', SecurityAuditEvent::PasswordUpdated->value)->exists())->toBeTrue()
        ->and(SecurityAuditLog::query()->where('user_id', $user->id)->where('event', SecurityAuditEvent::OtherSessionsRevoked->value)->exists())->toBeTrue();
});

test('authenticated user can revoke own other sessions but cannot revoke another users session (IDOR)', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'owner-second-device',
            'user_id' => $owner->id,
            'ip_address' => '192.168.1.25',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Edg/125.0',
            'payload' => 'a:0:{}',
            'last_activity' => time() - 60,
        ],
        [
            'id' => 'other-user-device',
            'user_id' => $otherUser->id,
            'ip_address' => '192.168.1.99',
            'user_agent' => 'Mozilla/5.0 (iPhone)',
            'payload' => 'a:0:{}',
            'last_activity' => time() - 60,
        ],
    ]);

    // Attempting to revoke another user's session ID does not delete it.
    $this->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('security.sessions.destroy', 'other-user-device'))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'other-user-device')->exists())->toBeTrue();

    // Revoking own other session deletes it cleanly.
    $this->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('security.sessions.destroy', 'owner-second-device'))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'owner-second-device')->exists())->toBeFalse()
        ->and(SecurityAuditLog::query()->where('user_id', $owner->id)->where('event', SecurityAuditEvent::SessionRevoked->value)->exists())->toBeTrue();
});

test('authorization matrix enforces least privilege for customer, staff, and admin roles and prevents mass assignment', function () {
    $customer = User::factory()->create();
    $staff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();
    $targetUser = User::factory()->create();

    // Guests are redirected to login.
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    // Mass-assignment attempt via Profile update or fill cannot elevate role.
    $this->actingAs($customer)
        ->patch(route('profile.update'), [
            'name' => 'Customer Updated',
            'email' => $customer->email,
            'phone' => '+63 917 888 9999',
            'role' => 'admin',
        ])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()?->role)->toBe(UserRole::Customer)
        ->and($customer->fresh()?->phone)->toBe('+63 917 888 9999');

    // Customer & Staff are both denied user/role administration (Admin-only).
    $this->actingAs($customer)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($customer)->patch(route('admin.users.role.update', $targetUser), ['role' => 'staff'])->assertForbidden();

    $this->actingAs($staff)->get(route('admin.catalog.index'))->assertOk();
    $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($staff)->patch(route('admin.users.role.update', $targetUser), ['role' => 'admin'])->assertForbidden();

    // Admin can access Users workspace and update another account's role, revoking target sessions.
    DB::table('sessions')->insert([
        'id' => 'target-session-xyz',
        'user_id' => $targetUser->id,
        'ip_address' => '10.1.1.1',
        'user_agent' => 'Mozilla/5.0',
        'payload' => 'a:0:{}',
        'last_activity' => time(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->has('users.data')
            ->has('recentAuditLogs')
        );

    $this->actingAs($admin)
        ->patch(route('admin.users.role.update', $targetUser), ['role' => 'staff'])
        ->assertRedirect();

    expect($targetUser->fresh()?->role)->toBe(UserRole::Staff)
        ->and(DB::table('sessions')->where('id', 'target-session-xyz')->exists())->toBeFalse()
        ->and(SecurityAuditLog::query()->where('user_id', $targetUser->id)->where('actor_id', $admin->id)->where('event', SecurityAuditEvent::RoleUpdated->value)->exists())->toBeTrue();

    // Admin cannot remove their own administrator privileges.
    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $admin), ['role' => 'customer'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors('role');

    expect($admin->fresh()?->role)->toBe(UserRole::Admin);
});

test('privileged MFA enforcement blocks unenrolled staff and admin while allowing MFA-protected admin accounts', function () {
    Config::set('fortify.require_mfa_for_privileged', true);

    $staffWithoutMfa = User::factory()->staff()->create();
    $adminWithTwoFactor = User::factory()->admin()->withTwoFactor()->create();
    $customer = User::factory()->create();

    $this->actingAs($staffWithoutMfa)
        ->get(route('admin.catalog.index'))
        ->assertRedirect(route('security.edit'));

    $this->actingAs($staffWithoutMfa)
        ->post(route('admin.catalog.brands.store'), ['name' => 'Blocked Brand'])
        ->assertForbidden();

    $this->actingAs($adminWithTwoFactor)
        ->get(route('admin.users.index'))
        ->assertOk();

    // Regular customer account routes are not blocked by privileged MFA rule.
    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertOk();
});

test('security audit logger automatically captures auth lifecycle events and redacts sensitive metadata keys', function () {
    $user = User::factory()->create(['password' => bcrypt('SecretPass!2026')]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(SecurityAuditLog::query()->where('user_id', $user->id)->where('event', SecurityAuditEvent::LoginFailed->value)->exists())->toBeTrue();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'SecretPass!2026',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(SecurityAuditLog::query()->where('user_id', $user->id)->where('event', SecurityAuditEvent::Login->value)->exists())->toBeTrue();

    $log = app(SecurityAuditLogger::class)->record(
        event: SecurityAuditEvent::VerificationCodeSent,
        user: $user,
        actor: $user,
        metadata: [
            'channel' => 'gmail',
            'verification_code' => '123456',
            'api_token' => 'secret-token-value',
        ],
    );

    expect($log->metadata)->toBe([
        'channel' => 'gmail',
        'verification_code' => '[redacted]',
        'api_token' => '[redacted]',
    ]);
});
