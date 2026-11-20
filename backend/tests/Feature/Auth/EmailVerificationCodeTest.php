<?php

use App\Domain\Identity\Actions\SendEmailVerificationCodeAction;
use App\Domain\Identity\Actions\VerifyEmailCodeAction;
use App\Domain\Identity\Enums\VerificationCodeStatus;
use App\Domain\Identity\Models\EmailVerificationCode;
use App\Domain\Identity\Notifications\EmailVerificationCodeNotification;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('send action issues a 6-digit hashed code and notifies the user gmail address without storing plaintext', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'name' => 'Maria Santos',
        'email' => 'Maria.Santos@Gmail.com',
    ]);

    Config::set('mail.verification_mailer', 'gmail');
    Config::set('mail.mailers.gmail.from_address', 'xhopy-noreply@gmail.com');

    $record = app(SendEmailVerificationCodeAction::class)->execute($user);

    expect($record->user_id)->toBe($user->id)
        ->and($record->email)->toBe('maria.santos@gmail.com')
        ->and($record->attempts)->toBe(0)
        ->and($record->consumed_at)->toBeNull()
        ->and($record->code_hash)->toHaveLength(64);

    $capturedCode = null;

    Notification::assertSentTo(
        $user,
        EmailVerificationCodeNotification::class,
        function (EmailVerificationCodeNotification $notification) use ($user, $record, &$capturedCode): bool {
            $capturedCode = $notification->code;
            $mail = $notification->toMail($user);

            expect($notification->mailer)->toBe('gmail')
                ->and($mail->mailer)->toBe('gmail')
                ->and($mail->from)->toBe(['xhopy-noreply@gmail.com', (string) config('mail.from.name')])
                ->and($notification->code)->toMatch('/^\d{6}$/')
                ->and($record->code_hash)->not->toBe($notification->code)
                ->and($record->code_hash)->toBe(SendEmailVerificationCodeAction::hashCode($notification->code));

            return true;
        },
    );

    expect($capturedCode)->not->toBeNull();
});

test('user helper sendEmailVerificationCode issues code and sends notification cleanly', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'helper.test@gmail.com',
    ]);

    expect($user->isUsingGmailAddress())->toBeTrue();

    $record = $user->sendEmailVerificationCode();

    expect($record)->toBeInstanceOf(EmailVerificationCode::class)
        ->and($record->email)->toBe('helper.test@gmail.com');

    Notification::assertSentTo($user, EmailVerificationCodeNotification::class);
});

test('resolveMailer automatically picks gmail when gmail credentials are configured outside array mode', function () {
    Config::set('mail.default', 'log');
    Config::set('mail.verification_mailer', null);
    Config::set('mail.mailers.gmail.username', null);
    Config::set('mail.mailers.gmail.password', null);

    expect(SendEmailVerificationCodeAction::resolveMailer())->toBe('log');

    Config::set('mail.mailers.gmail.username', 'sender@gmail.com');
    Config::set('mail.mailers.gmail.password', 'example-app-password');

    expect(SendEmailVerificationCodeAction::resolveMailer())->toBe('gmail')
        ->and(SendEmailVerificationCodeAction::resolveMailer('smtp'))->toBe('smtp');
});

test('sending a new code invalidates previous unconsumed verification codes', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'resend@gmail.com',
    ]);

    $firstCode = null;
    $secondCode = null;

    $firstRecord = app(SendEmailVerificationCodeAction::class)->execute($user);
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (EmailVerificationCodeNotification $notification) use (&$firstCode): bool {
        $firstCode = $notification->code;

        return true;
    });

    $secondRecord = app(SendEmailVerificationCodeAction::class)->execute($user);
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (EmailVerificationCodeNotification $notification) use (&$secondCode, $firstCode): bool {
        if ($notification->code !== $firstCode) {
            $secondCode = $notification->code;
        }

        return true;
    });

    expect($firstRecord->fresh()?->consumed_at)->not->toBeNull()
        ->and($secondRecord->fresh()?->consumed_at)->toBeNull();

    $verifyAction = app(VerifyEmailCodeAction::class);

    expect($verifyAction->execute($user, (string) $firstCode))->toBe(VerificationCodeStatus::InvalidCode)
        ->and($user->fresh()?->hasVerifiedEmail())->toBeFalse()
        ->and($verifyAction->execute($user, (string) $secondCode))->toBe(VerificationCodeStatus::Verified)
        ->and($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

test('strict gmail mode rejects non-gmail addresses on action and http endpoint', function () {
    Notification::fake();
    Config::set('mail.gmail_verification_only', true);

    $user = User::factory()->unverified()->create([
        'email' => 'customer@example.org',
    ]);

    expect(fn () => app(SendEmailVerificationCodeAction::class)->execute($user))
        ->toThrow(InvalidArgumentException::class);

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.code.send'))
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

test('verify email notice page shares gmail metadata cleanly', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'notice.buyer@gmail.com',
    ]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/VerifyEmail')
            ->where('email', 'notice.buyer@gmail.com')
            ->where('isGmail', true)
            ->where('codeExpiresInMinutes', SendEmailVerificationCodeAction::TTL_MINUTES)
        );
});

test('authenticated user can request and verify a gmail code via http endpoints', function () {
    Notification::fake();
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create([
        'email' => 'checkout.user@gmail.com',
    ]);

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.code.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-code-sent');

    $issuedCode = null;
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (EmailVerificationCodeNotification $notification) use (&$issuedCode): bool {
        $issuedCode = $notification->code;

        return true;
    });

    $spacedCode = substr((string) $issuedCode, 0, 3).' '.substr((string) $issuedCode, 3, 3);

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.code.verify'), [
            'code' => $spacedCode,
        ])
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

test('expired verification code is rejected and marked consumed', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'expired.user@gmail.com',
    ]);

    $record = app(SendEmailVerificationCodeAction::class)->execute($user);

    $issuedCode = null;
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (EmailVerificationCodeNotification $notification) use (&$issuedCode): bool {
        $issuedCode = $notification->code;

        return true;
    });

    Date::setTestNow(Date::now()->addMinutes(SendEmailVerificationCodeAction::TTL_MINUTES + 1));

    try {
        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.code.verify'), [
                'code' => (string) $issuedCode,
            ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('code');

        expect($user->fresh()?->hasVerifiedEmail())->toBeFalse()
            ->and($record->fresh()?->consumed_at)->not->toBeNull();
    } finally {
        Date::setTestNow();
    }
});

test('five incorrect attempts lock and invalidate the active verification code', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'attempts@gmail.com',
    ]);

    $record = app(SendEmailVerificationCodeAction::class)->execute($user);

    $issuedCode = null;
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (EmailVerificationCodeNotification $notification) use (&$issuedCode): bool {
        $issuedCode = $notification->code;

        return true;
    });

    $wrongCode = $issuedCode === '000000' ? '111111' : '000000';
    $verifyAction = app(VerifyEmailCodeAction::class);

    for ($i = 1; $i <= 4; $i++) {
        expect($verifyAction->execute($user, $wrongCode))->toBe(VerificationCodeStatus::InvalidCode);
    }

    expect($verifyAction->execute($user, $wrongCode))->toBe(VerificationCodeStatus::TooManyAttempts)
        ->and($record->fresh()?->attempts)->toBe(5)
        ->and($record->fresh()?->consumed_at)->not->toBeNull();

    // Even presenting the originally valid code fails after lockout.
    expect($verifyAction->execute($user, (string) $issuedCode))->toBe(VerificationCodeStatus::InvalidCode)
        ->and($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

test('verification code issued for a previous email cannot verify a changed email address', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'first@gmail.com',
    ]);

    app(SendEmailVerificationCodeAction::class)->execute($user);

    $issuedCode = null;
    Notification::assertSentTo($user, EmailVerificationCodeNotification::class, function (EmailVerificationCodeNotification $notification) use (&$issuedCode): bool {
        $issuedCode = $notification->code;

        return true;
    });

    $user->forceFill(['email' => 'second@gmail.com'])->save();

    expect(app(VerifyEmailCodeAction::class)->execute($user, (string) $issuedCode))
        ->toBe(VerificationCodeStatus::InvalidCode)
        ->and($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

test('guests cannot request or submit verification codes and endpoints enforce rate limits', function () {
    Notification::fake();

    $this->post(route('verification.code.send'))
        ->assertRedirect(route('login'));

    $this->post(route('verification.code.verify'), ['code' => '123456'])
        ->assertRedirect(route('login'));

    $user = User::factory()->unverified()->create([
        'email' => 'throttle@gmail.com',
    ]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->actingAs($user)
            ->post(route('verification.code.send'))
            ->assertRedirect();
    }

    $this->actingAs($user)
        ->post(route('verification.code.send'))
        ->assertTooManyRequests();
});

test('already verified user redirects cleanly without sending a new verification code', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'verified.user@gmail.com',
    ]);

    $this->actingAs($user)
        ->post(route('verification.code.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});
