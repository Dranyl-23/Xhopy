<?php

namespace App\Domain\Orders\Notifications;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\SellerOrder;
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
        public readonly ?string $sellerName = null,
        public readonly ?string $sellerOrderNumber = null,
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

    public static function fromSellerOrder(Order $order, SellerOrder $sellerOrder, ?string $reason = null): self
    {
        return new self(
            orderId: $order->id,
            orderNumber: $order->order_number,
            statusValue: $sellerOrder->status->value,
            statusLabel: $sellerOrder->status->label(),
            totalFormatted: MoneyFormatter::format($sellerOrder->total_amount, $sellerOrder->currency),
            reason: $reason,
            shippingCarrier: $sellerOrder->shipping_carrier,
            trackingNumber: $sellerOrder->tracking_number,
            sellerName: $sellerOrder->seller_name,
            sellerOrderNumber: $sellerOrder->seller_order_number,
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

        $subjectNumber = $this->sellerOrderNumber ?? $this->orderNumber;
        $lineSubject = $this->sellerName !== null && $this->sellerOrderNumber !== null
            ? "Fulfillment group **{$this->sellerOrderNumber}** from **{$this->sellerName}** in order **{$this->orderNumber}** (Subtotal: {$this->totalFormatted}) has been updated to **{$this->statusLabel}**."
            : "Your order **{$this->orderNumber}** (Total: {$this->totalFormatted}) has been updated to **{$this->statusLabel}**.";

        $message = (new MailMessage)
            ->subject("{$appName} Order {$subjectNumber}: {$this->statusLabel}")
            ->greeting("Hello {$recipientName},")
            ->line($lineSubject);

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
        $titlePrefix = $this->sellerOrderNumber !== null
            ? "Order {$this->orderNumber} ({$this->sellerName}): {$this->statusLabel}"
            : "Order {$this->orderNumber}: {$this->statusLabel}";

        return [
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'seller_order_number' => $this->sellerOrderNumber,
            'seller_name' => $this->sellerName,
            'status' => $this->statusValue,
            'status_label' => $this->statusLabel,
            'total_formatted' => $this->totalFormatted,
            'title' => $titlePrefix,
            'message' => $this->reason !== null && trim($this->reason) !== ''
                ? $this->reason
                : "Order status updated to {$this->statusLabel}.",
            'shipping_carrier' => $this->shippingCarrier,
            'tracking_number' => $this->trackingNumber,
            'action_url' => route('orders.show', ['order' => $this->orderId], false),
        ];
    }
}
