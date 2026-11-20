import type { PaginationMeta } from '@modules/catalog';

export interface AdminProductRow {
    id: number;
    name: string;
    slug: string;
    sku: string;
    status: string;
    statusLabel: string;
    isFeatured: boolean;
    categoryName: string;
    brandName?: string | null;
    priceFormatted: string;
    stockQuantity: number;
    variantCount: number;
    imageUrl?: string | null;
}

export interface AdminCatalogIndexProps {
    products: AdminProductRow[];
    pagination: PaginationMeta;
    filters: {
        q: string;
        status: string;
    };
    stats: {
        totalProducts: number;
        activeProducts: number;
        totalCategories: number;
        totalBrands: number;
    };
}

export interface AdminVariantItem {
    id?: number;
    title: string;
    sku: string;
    price: string;
    compare_at_price: string;
    stock_quantity: number;
    options: Record<string, string>;
    is_default: boolean;
    is_active: boolean;
}

export interface AdminImageItem {
    id: number;
    variant_id: number | null;
    url: string;
    alt_text: string;
    is_primary: boolean;
}

export interface AdminProductEditable {
    id: number;
    category_id: number;
    brand_id: number | null;
    seller_name: string;
    name: string;
    slug: string;
    sku: string;
    short_description: string;
    description: string;
    status: string;
    is_featured: boolean;
    currency: string;
    base_price: string;
    compare_at_price: string;
    stock_quantity: number;
    specifications: Record<string, string>;
    variants: AdminVariantItem[];
    images: AdminImageItem[];
}

export interface AdminSelectOption {
    id: number;
    name: string;
    slug?: string;
    is_active?: boolean;
}

export interface AdminStatusOption {
    value: string;
    label: string;
}

export interface AdminProductFormProps {
    product: AdminProductEditable | null;
    categories: AdminSelectOption[];
    brands: AdminSelectOption[];
    statuses: AdminStatusOption[];
}

export interface AdminCategoryRow {
    id: number;
    parent_id: number | null;
    parent_name?: string | null;
    name: string;
    slug: string;
    description: string;
    sort_order: number;
    is_active: boolean;
    products_count: number;
}

export interface AdminBrandRow {
    id: number;
    name: string;
    slug: string;
    description: string;
    website_url: string;
    is_active: boolean;
    products_count: number;
}

export interface AdminCampaignRow {
    id: number;
    placement: string;
    placementLabel: string;
    tag: string;
    title: string;
    summary: string;
    cta_label: string;
    cta_href: string;
    category_id: number | null;
    category_name?: string | null;
    sort_order: number;
    is_active: boolean;
}

export interface AdminUserRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    role_label: string;
    email_verified: boolean;
    has_two_factor: boolean;
    has_passkeys: boolean;
    orders_count?: number;
    total_spent_formatted?: string;
    created_at_diff: string;
}

export interface AdminAuditLogRow {
    id: number;
    event: string;
    label: string;
    user_name: string;
    user_email: string;
    actor_name: string;
    ip_address: string;
    metadata: Record<string, unknown> | null;
    created_at_diff: string;
}

export interface AdminAnalyticsSummary {
    kpis: {
        grossRevenueFormatted: string;
        avgOrderValueFormatted: string;
        totalOrders: number;
        confirmedOrdersCount: number;
        pendingPaymentCount: number;
        fulfillmentQueueCount: number;
        shippedOrDeliveredCount: number;
        cancelledOrRefundedCount: number;
        lowStockCount: number;
        outOfStockCount: number;
    };
    dailyTrend: Array<{
        date: string;
        label: string;
        orders: number;
        revenueCentavos: number;
        revenueFormatted: string;
        percentOfPeak: number;
    }>;
    topSellingProducts: Array<{
        product_name: string;
        sku: string;
        units_sold: number;
        gross_sales_formatted: string;
    }>;
    lowStockItems: Array<{
        product_id: number;
        name: string;
        sku: string;
        stock_quantity: number;
        variant_count: number;
    }>;
}

export interface AdminOrderListRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    items_count: number;
    total_formatted: string;
    placed_at_diff: string;
    can_advance_to_processing: boolean;
}

export interface AdminOrderDetail {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    subtotal_formatted: string;
    shipping_formatted: string;
    total_formatted: string;
    customer: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
    };
    shipping_recipient_name: string;
    shipping_phone: string;
    shipping_address: string;
    shipping_delivery_notes: string | null;
    notes: string | null;
    shipping_carrier: string | null;
    tracking_number: string | null;
    placed_at_diff: string;
    paid_at_diff: string | null;
    shipped_at_diff: string | null;
    delivered_at_diff: string | null;
    cancelled_at_diff: string | null;
    refunded_at_diff: string | null;
    allowed_transitions: AdminStatusOption[];
    items: Array<{
        id: number;
        product_name: string;
        variant_title: string | null;
        sku: string;
        seller_name: string;
        quantity: number;
        unit_price_formatted: string;
        line_total_formatted: string;
    }>;
    payments: Array<{
        id: number;
        provider: string;
        provider_reference: string | null;
        status_label: string;
        amount_formatted: string;
        payment_method_type: string | null;
        paid_at_diff: string | null;
    }>;
    status_history: Array<{
        id: number;
        to_status_label: string;
        actor_name: string;
        reason: string | null;
        created_at_diff: string;
    }>;
}

export interface AdminInventorySkuRow {
    key: string;
    product_id: number;
    product_variant_id: number | null;
    name: string;
    variant_title: string | null;
    sku: string;
    category_name: string;
    status_label: string;
    stock_quantity: number;
}

export interface AdminStockMovementRow {
    id: number;
    product_name: string;
    variant_title: string | null;
    sku: string;
    type: string;
    type_label: string;
    quantity_delta: number;
    stock_after: number;
    reference: string;
    order_id: number | null;
    order_number: string | null;
    actor_name: string;
    created_at_diff: string;
}
