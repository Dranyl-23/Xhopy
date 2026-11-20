<?php

use App\Domain\Identity\Actions\SendEmailVerificationCodeAction;
use App\Domain\Identity\Enums\VerificationCodeStatus;

test('gmail address detection normalizes whitespace and casing for gmail and googlemail domains', function () {
    expect(SendEmailVerificationCodeAction::normalizeEmail('  Alice.Doe@Gmail.COM '))->toBe('alice.doe@gmail.com')
        ->and(SendEmailVerificationCodeAction::isGmailAddress('buyer@gmail.com'))->toBeTrue()
        ->and(SendEmailVerificationCodeAction::isGmailAddress(' BUYER+xhopy@GMAIL.COM '))->toBeTrue()
        ->and(SendEmailVerificationCodeAction::isGmailAddress('buyer@googlemail.com'))->toBeTrue()
        ->and(SendEmailVerificationCodeAction::isGmailAddress('buyer@yahoo.com'))->toBeFalse()
        ->and(SendEmailVerificationCodeAction::isGmailAddress('gmail.com'))->toBeFalse()
        ->and(SendEmailVerificationCodeAction::isGmailAddress(''))->toBeFalse();
});

test('verification code status enum provides safe non-sensitive user messages', function () {
    expect(VerificationCodeStatus::Verified->message())->toBe('Your email address has been verified.')
        ->and(VerificationCodeStatus::AlreadyVerified->message())->toBe('Your email address is already verified.')
        ->and(VerificationCodeStatus::InvalidCode->message())->toBe('The verification code you entered is invalid.')
        ->and(VerificationCodeStatus::Expired->message())->toBe('This verification code has expired. Please request a new code.')
        ->and(VerificationCodeStatus::TooManyAttempts->message())->toBe('Too many incorrect attempts. Please request a new verification code.');
});
