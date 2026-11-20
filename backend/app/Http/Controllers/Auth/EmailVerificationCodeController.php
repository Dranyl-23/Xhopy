<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\SendEmailVerificationCodeAction;
use App\Domain\Identity\Actions\VerifyEmailCodeAction;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\VerificationCodeStatus;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class EmailVerificationCodeController extends Controller
{
    /**
     * Send a 6-digit email verification code to the authenticated user's email/Gmail address.
     */
    public function store(Request $request, SendEmailVerificationCodeAction $sendCode, SecurityAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $sendCode->execute($user);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ]);
        }

        $auditLogger->record(
            event: SecurityAuditEvent::VerificationCodeSent,
            user: $user,
            actor: $user,
            request: $request,
        );

        return back()->with('status', 'verification-code-sent');
    }

    /**
     * Verify the authenticated user's email address using a submitted 6-digit verification code.
     */
    public function verify(VerifyEmailCodeRequest $request, VerifyEmailCodeAction $verifyCode): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $status = $verifyCode->execute($user, (string) $request->validated('code'));

        return match ($status) {
            VerificationCodeStatus::AlreadyVerified => redirect()->intended(route('dashboard', absolute: false)),
            VerificationCodeStatus::Verified => redirect()->intended(route('dashboard', absolute: false).'?verified=1'),
            VerificationCodeStatus::InvalidCode,
            VerificationCodeStatus::Expired,
            VerificationCodeStatus::TooManyAttempts => throw ValidationException::withMessages([
                'code' => $status->message(),
            ]),
        };
    }
}
