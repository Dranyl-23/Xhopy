<?php

namespace App\Domain\Identity\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

final class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(
        private readonly string $status,
    ) {}

    /**
     * Return an enumeration-safe response unless rate-limited by password broker throttling.
     *
     * @throws ValidationException
     */
    public function toResponse($request): Response
    {
        if ($this->status === Password::RESET_THROTTLED) {
            if ($request->wantsJson()) {
                throw ValidationException::withMessages([
                    'email' => [trans($this->status)],
                ]);
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => trans($this->status)]);
        }

        $safeMessage = __('If an account matches that email address, a password reset link has been sent.');

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $safeMessage], 200);
        }

        return $this->redirectResponse($safeMessage);
    }

    private function redirectResponse(string $message): RedirectResponse
    {
        return back()->with('status', $message);
    }
}
