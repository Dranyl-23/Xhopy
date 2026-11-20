<?php

namespace App\Domain\Orders\Notifications;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $orderNumber,
        public readonly string $statusValue,
        public readonly string $statusLabel,
        public readonly string $totalFormatted,
        public readonly ?string $reason = null,
        public readonly ?string $shippingCarrier = null,
        public readonly ?string $trackingNumber = null,
    ) {}

    public static function fromOrder(Order $order, ?string $reason = null): self
    {
        return new self(
            orderId: $order->id,
            orderNumber: $order->order_number,
            statusValue: $order->status->value,
            statusLabel: $order->status->label(),
            totalFormatted: MoneyFormatter::format($order->total_amount, $order->currency),
            reason: $reason,
            shippingCarrier: $order->shipping_carrier,
            trackingNumber: $order->tracking_number,
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
            : 'Customer';

        $appName = (string) config('app.name', 'XHopy');

        $message = (new MailMessage)
            ->subject("{$appName} Order {$this->orderNumber}: {$this->statusLabel}")
            ->greeting("Hello {$recipientName},")
            ->line("Your order **{$this->orderNumber}** (Total: {$this->totalFormatted}) has been updated to **{$this->statusLabel}**.");

        if ($this->statusValue === OrderStatus::Shipped->value && ($this->shippingCarrier !== null || $this->trackingNumber !== null)) {
            $trackingParts = array_filter([
                $this->shippingCarrier !== null && $this->shippingCarrier !== '' ? "Carrier: {$this->shippingCarrier}" : null,
                $this->trackingNumber !== null && $this->trackingNumber !== '' ? "Tracking #: {$this->trackingNumber}" : null,
            ]);

            if ($trackingParts !== []) {
                $message->line(implode(' · ', $trackingParts));
            }
        }

        if ($this->reason !== null && trim($this->reason) !== '') {
            $message->line($this->reason);
        }

        return $message
            ->action('View Order Details', route('orders.show', ['order' => $this->orderId]))
            ->line("Thank you for shopping with {$appName}.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'status' => $this->statusValue,
            'status_label' => $this->statusLabel,
            'total_formatted' => $this->totalFormatted,
            'title' => "Order {$this->orderNumber}: {$this->statusLabel}",
            'message' => $this->reason !== null && trim($this->reason) !== ''
                ? $this->reason
                : "Order status updated to {$this->statusLabel}.",
            'shipping_carrier' => $this->shippingCarrier,
            'tracking_number' => $this->trackingNumber,
            'action_url' => route('orders.show', ['order' => $this->orderId], false),
        ];
    }
}
