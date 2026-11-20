<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\EmailVerificationCode;
use App\Domain\Identity\Notifications\EmailVerificationCodeNotification;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class SendEmailVerificationCodeAction
{
    public const int CODE_LENGTH = 6;

    public const int TTL_MINUTES = 10;

    /**
     * @var list<string>
     */
    private const array GMAIL_DOMAINS = [
        'gmail.com',
        'googlemail.com',
    ];

    /**
     * Generate a single-use 6-digit verification code and send it to the user's email/Gmail address.
     */
    public function execute(User $user, ?string $mailer = null, ?bool $requireGmail = null): EmailVerificationCode
    {
        $normalizedEmail = self::normalizeEmail($user->email);

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('User email address is invalid.');
        }

        $strictGmail = $requireGmail ?? (bool) config('mail.gmail_verification_only', false);

        if ($strictGmail && ! self::isGmailAddress($normalizedEmail)) {
            throw new InvalidArgumentException('Verification codes can only be sent to a Gmail address (@gmail.com or @googlemail.com).');
        }

        $plainCode = str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );

        $codeHash = self::hashCode($plainCode);
        $now = Date::now();

        /** @var EmailVerificationCode $verificationCode */
        $verificationCode = DB::transaction(function () use ($user, $normalizedEmail, $codeHash, $now): EmailVerificationCode {
            EmailVerificationCode::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->update(['consumed_at' => $now]);

            return EmailVerificationCode::query()->create([
                'user_id' => $user->id,
                'email' => $normalizedEmail,
                'code_hash' => $codeHash,
                'attempts' => 0,
                'expires_at' => $now->addMinutes(self::TTL_MINUTES),
            ]);
        });

        $resolvedMailer = self::resolveMailer($mailer);

        $user->notify(new EmailVerificationCodeNotification(
            code: $plainCode,
            ttlMinutes: self::TTL_MINUTES,
            mailer: $resolvedMailer,
        ));

        return $verificationCode;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isGmailAddress(string $email): bool
    {
        $normalized = self::normalizeEmail($email);

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $atPosition = strrpos($normalized, '@');
        if ($atPosition === false) {
            return false;
        }

        $domain = substr($normalized, $atPosition + 1);

        return in_array($domain, self::GMAIL_DOMAINS, true);
    }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', trim($code), self::hmacKey());
    }

    public static function resolveMailer(?string $explicitMailer = null): string
    {
        if (is_string($explicitMailer) && trim($explicitMailer) !== '') {
            return trim($explicitMailer);
        }

        $configuredVerificationMailer = config('mail.verification_mailer');
        if (is_string($configuredVerificationMailer) && trim($configuredVerificationMailer) !== '') {
            return trim($configuredVerificationMailer);
        }

        $defaultMailer = (string) config('mail.default', 'log');
        if ($defaultMailer === 'gmail') {
            return 'gmail';
        }

        $gmailUsername = config('mail.mailers.gmail.username');
        $gmailPassword = config('mail.mailers.gmail.password');
        $hasGmailCredentials = is_string($gmailUsername)
            && trim($gmailUsername) !== ''
            && is_string($gmailPassword)
            && trim($gmailPassword) !== '';

        if ($defaultMailer !== 'array' && $hasGmailCredentials) {
            return 'gmail';
        }

        return $defaultMailer !== '' ? $defaultMailer : 'log';
    }

    private static function hmacKey(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Application key is not configured for verification code hashing.');
        }

        return $key;
    }
}
