export type { Auth, User } from '@shared/types/auth';

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};

export interface CustomerAddress {
    id: number;
    label: string;
    recipient_name: string;
    phone: string;
    country_code: string;
    province: string;
    city: string;
    barangay: string;
    postal_code: string;
    address_line_1: string;
    address_line_2: string;
    delivery_notes: string;
    is_default_shipping: boolean;
    is_default_billing: boolean;
    formatted_line: string;
}

export interface ActiveSession {
    id: string;
    ip_address: string;
    device: string;
    is_current: boolean;
    last_active_diff: string;
}

export interface SecurityAuditEntry {
    id: number;
    event?: string;
    label: string;
    ip_address: string;
    device?: string;
    created_at_diff: string;
}

export interface CustomerPaymentEntry {
    id: number;
    order_id: number;
    order_number: string;
    provider: string;
    payment_method_type: string | null;
    status: string;
    status_label: string;
    amount_formatted: string;
    activity_at_diff: string;
}

export interface AccountDashboardSummary {
    roleLabel: string;
    emailVerified: boolean;
    hasMfaEnabled: boolean;
    addressCount: number;
    orderCount: number;
    wishlistCount?: number;
    totalSpentFormatted?: string;
    unreadNotificationsCount?: number;
    defaultShippingAddress: {
        label: string;
        recipient_name: string;
        phone: string;
        formatted_line: string;
    } | null;
    recentOrders?: OrderListItem[];
    recentPayments?: CustomerPaymentEntry[];
    recentSecurityEvents: SecurityAuditEntry[];
}

export interface OrderListItem {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    items_count: number;
    seller_orders_count?: number;
    total_formatted: string;
    placed_at_diff: string;
}

export interface CustomerSellerOrderGroup {
    id: number;
    seller_order_number: string;
    seller_name: string;
    store_slug: string | null;
    store_url: string | null;
    is_official_store: boolean;
    status: string;
    status_label: string;
    subtotal_formatted: string;
    shipping_formatted: string;
    total_formatted: string;
    shipping_carrier: string | null;
    tracking_number: string | null;
    shipped_at_diff: string | null;
    delivered_at_diff: string | null;
    cancelled_at_diff: string | null;
    refunded_at_diff: string | null;
    items: OrderSnapshotItem[];
}

export interface OrderSnapshotItem {
    id: number;
    product_name: string;
    variant_title: string | null;
    sku: string;
    seller_name: string;
    quantity: number;
    unit_price_formatted: string;
    line_total_formatted: string;
}

export interface OrderStatusHistoryEntry {
    id: number;
    to_status_label: string;
    reason: string | null;
    created_at_diff: string;
}

export interface OrderDetailRecord {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    subtotal_formatted: string;
    shipping_formatted: string;
    total_formatted: string;
    shipping_recipient_name: string;
    shipping_phone: string;
    shipping_address: string;
    shipping_delivery_notes: string | null;
    notes: string | null;
    shipping_carrier?: string | null;
    tracking_number?: string | null;
    placed_at_diff: string;
    paid_at_diff: string | null;
    shipped_at_diff?: string | null;
    delivered_at_diff?: string | null;
    refunded_at_diff?: string | null;
    can_cancel: boolean;
    can_retry_payment: boolean;
    payment_method_type: string | null;
    seller_orders?: CustomerSellerOrderGroup[];
    items: OrderSnapshotItem[];
    status_history: OrderStatusHistoryEntry[];
}

export interface CustomerNotificationEntry {
    id: string;
    title: string;
    message: string;
    action_url: string | null;
    is_unread: boolean;
    created_at_diff: string;
}
