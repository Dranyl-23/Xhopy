<?php

namespace App\Domain\Seller\Notifications;

use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SellerApplicationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $sellerProfileId,
        public readonly string $businessName,
        public readonly string $storeName,
        public readonly string $statusValue,
        public readonly string $statusLabel,
        public readonly ?string $reason = null,
    ) {}

    public static function fromProfile(SellerProfile $profile, ?string $reason = null): self
    {
        $storeName = $profile->store !== null && trim($profile->store->name) !== ''
            ? $profile->store->name
            : $profile->business_name;

        return new self(
            sellerProfileId: $profile->id,
            businessName: $profile->business_name,
            storeName: $storeName,
            statusValue: $profile->status->value,
            statusLabel: $profile->status->label(),
            reason: $reason,
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $notifiable instanceof User && trim($notifiable->name) !== ''
            ? trim($notifiable->name)
            : 'Seller';

        $appName = (string) config('app.name', 'XHopy');
        $isApproved = $this->statusValue === SellerStatus::Approved->value;

        $message = (new MailMessage)
            ->subject("{$appName} Seller Application: {$this->statusLabel}")
            ->greeting("Hello {$recipientName},")
            ->line("Your seller application for **{$this->storeName}** ({$this->businessName}) is now **{$this->statusLabel}**.");

        if ($this->reason !== null && trim($this->reason) !== '') {
            $message->line("Review note: {$this->reason}");
        }

        if ($isApproved) {
            return $message
                ->action('Open Seller Portal', route('seller.dashboard'))
                ->line("Welcome to selling on {$appName}.");
        }

        return $message
            ->action('View Application Status', route('seller.onboarding'))
            ->line("You can review the current verification details on your {$appName} seller onboarding page.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isApproved = $this->statusValue === SellerStatus::Approved->value;

        return [
            'seller_profile_id' => $this->sellerProfileId,
            'business_name' => $this->businessName,
            'store_name' => $this->storeName,
            'status' => $this->statusValue,
            'status_label' => $this->statusLabel,
            'reason' => $this->reason,
            'title' => "Seller application: {$this->statusLabel}",
            'url' => route($isApproved ? 'seller.dashboard' : 'seller.onboarding', absolute: false),
        ];
    }
}
