<?php

namespace App\Domain\Identity\Enums;

enum VerificationCodeStatus: string
{
    case Verified = 'verified';
    case AlreadyVerified = 'already_verified';
    case InvalidCode = 'invalid_code';
    case Expired = 'expired';
    case TooManyAttempts = 'too_many_attempts';

    public function message(): string
    {
        return match ($this) {
            self::Verified => 'Your email address has been verified.',
            self::AlreadyVerified => 'Your email address is already verified.',
            self::InvalidCode => 'The verification code you entered is invalid.',
            self::Expired => 'This verification code has expired. Please request a new code.',
            self::TooManyAttempts => 'Too many incorrect attempts. Please request a new verification code.',
        };
    }
}
