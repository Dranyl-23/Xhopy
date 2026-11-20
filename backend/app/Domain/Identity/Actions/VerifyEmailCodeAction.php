<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\VerificationCodeStatus;
use App\Domain\Identity\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class VerifyEmailCodeAction
{
    public const int MAX_ATTEMPTS = 5;

    /**
     * Validate a 6-digit email verification code and mark the user's email verified when matched.
     */
    public function execute(User $user, string $code): VerificationCodeStatus
    {
        if ($user->hasVerifiedEmail()) {
            return VerificationCodeStatus::AlreadyVerified;
        }

        $normalizedEmail = SendEmailVerificationCodeAction::normalizeEmail($user->email);
        $normalizedCode = preg_replace('/\s+/', '', trim($code)) ?? '';

        if ($normalizedCode === '') {
            return VerificationCodeStatus::InvalidCode;
        }

        $expectedHash = SendEmailVerificationCodeAction::hashCode($normalizedCode);

        /** @var VerificationCodeStatus $status */
        $status = DB::transaction(function () use ($user, $normalizedEmail, $expectedHash): VerificationCodeStatus {
            $now = Date::now();

            /** @var EmailVerificationCode|null $record */
            $record = EmailVerificationCode::query()
                ->where('user_id', $user->id)
                ->where('email', $normalizedEmail)
                ->whereNull('consumed_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                return VerificationCodeStatus::InvalidCode;
            }

            if ($record->isExpired()) {
                $record->forceFill(['consumed_at' => $now])->save();

                return VerificationCodeStatus::Expired;
            }

            if ($record->hasReachedMaxAttempts(self::MAX_ATTEMPTS)) {
                $record->forceFill(['consumed_at' => $now])->save();

                return VerificationCodeStatus::TooManyAttempts;
            }

            if (! hash_equals($record->code_hash, $expectedHash)) {
                $nextAttempts = $record->attempts + 1;
                $updates = ['attempts' => $nextAttempts];

                if ($nextAttempts >= self::MAX_ATTEMPTS) {
                    $updates['consumed_at'] = $now;
                }

                $record->forceFill($updates)->save();

                return $nextAttempts >= self::MAX_ATTEMPTS
                    ? VerificationCodeStatus::TooManyAttempts
                    : VerificationCodeStatus::InvalidCode;
            }

            $record->forceFill(['consumed_at' => $now])->save();

            EmailVerificationCode::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now]);

            $user->markEmailAsVerified();

            return VerificationCodeStatus::Verified;
        });

        if ($status === VerificationCodeStatus::Verified) {
            event(new Verified($user));
        }

        return $status;
    }
}
