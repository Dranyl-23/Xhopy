import type { CartSummary, PresentedCart } from '@modules/cart';

export interface CheckoutAddressOption {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    is_default_shipping: boolean;
    formatted_line: string;
}

export interface CheckoutOrderItem {
    id: number;
    product_name: string;
    variant_title: string | null;
    sku: string;
    quantity: number;
    unit_price_formatted: string;
    line_total_formatted: string;
}

export interface CheckoutOrderSummary {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    total_formatted: string;
    payment_method_type: string | null;
    shipping_recipient_name: string;
    shipping_address: string;
    placed_at_diff: string;
    items: CheckoutOrderItem[];
}

export interface CheckoutIndexProps {
    cart: PresentedCart;
    cartSummary: CartSummary;
    addresses: CheckoutAddressOption[];
    defaultAddressId: number | null;
    idempotencyKey: string;
}

export interface CheckoutResultProps {
    outcome: 'success' | 'cancelled';
    order: CheckoutOrderSummary;
}

export interface CheckoutSandboxProps {
    order: CheckoutOrderSummary;
}
