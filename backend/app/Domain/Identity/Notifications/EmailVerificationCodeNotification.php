<?php

namespace App\Domain\Identity\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmailVerificationCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes = 10,
        public readonly ?string $mailer = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $notifiable instanceof User && trim($notifiable->name) !== ''
            ? trim($notifiable->name)
            : 'there';

        $appName = (string) config('app.name', 'XHopy');

        $message = (new MailMessage)
            ->subject($appName.' email verification code')
            ->greeting('Hello '.$recipientName.',')
            ->line('Enter the verification code below to verify your email address on '.$appName.':')
            ->line('**'.$this->code.'**')
            ->line('This verification code expires in '.$this->ttlMinutes.' minutes and can only be used once.')
            ->line('If you did not create or update an account on '.$appName.', you can safely ignore this email.');

        if ($this->mailer !== null && $this->mailer !== '') {
            $message->mailer($this->mailer);

            if ($this->mailer === 'gmail') {
                $gmailFrom = config('mail.mailers.gmail.from_address');
                $fromName = (string) config('mail.from.name', $appName);

                if (is_string($gmailFrom) && trim($gmailFrom) !== '') {
                    $message->from(trim($gmailFrom), $fromName);
                }
            }
        }

        return $message;
    }
}
