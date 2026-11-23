<?php

namespace App\Domain\Catalog\Notifications;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProductModerationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $sku,
        public readonly string $statusValue,
        public readonly string $statusLabel,
        public readonly ?string $reason = null,
    ) {}

    public static function fromProduct(Product $product, ?string $reason = null): self
    {
        return new self(
            productId: $product->id,
            productName: $product->name,
            sku: $product->sku,
            statusValue: $product->status->value,
            statusLabel: $product->status->label(),
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
        $isApproved = $this->statusValue === ProductStatus::Active->value;

        $message = (new MailMessage)
            ->subject("{$appName} Product Moderation: {$this->productName} ({$this->statusLabel})")
            ->greeting("Hello {$recipientName},")
            ->line("Your product listing **{$this->productName}** (SKU: {$this->sku}) has been updated to **{$this->statusLabel}**.");

        if ($this->reason !== null && trim($this->reason) !== '') {
            $message->line("Moderator feedback: {$this->reason}");
        }

        return $message
            ->action($isApproved ? 'View Product in Seller Portal' : 'Review Listing & Feedback', route('seller.products.edit', ['product' => $this->productId]))
            ->line($isApproved ? "This product is now live on the {$appName} marketplace." : 'Please update the product details and resubmit for moderation.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'sku' => $this->sku,
            'status' => $this->statusValue,
            'status_label' => $this->statusLabel,
            'reason' => $this->reason,
            'title' => "Product moderation: {$this->productName} ({$this->statusLabel})",
            'url' => route('seller.products.edit', ['product' => $this->productId], absolute: false),
        ];
    }
}
