import type { PaginationMeta } from '@modules/catalog';

export interface AdminProductRow {
    id: number;
    name: string;
    slug: string;
    sku: string;
    status: string;
    statusLabel: string;
    moderationNotes?: string | null;
    isFeatured: boolean;
    storeId?: number | null;
    storeName?: string;
    isOfficialStore?: boolean;
    categoryName: string;
    brandName?: string | null;
    priceFormatted: string;
    stockQuantity: number;
    variantCount: number;
    imageUrl?: string | null;
    submittedForReviewAtDiff?: string | null;
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
        pendingReviewProducts?: number;
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
    store_id?: number | null;
    store_name?: string;
    is_official_store?: boolean;
    seller_name: string;
    name: string;
    slug: string;
    sku: string;
    short_description: string;
    description: string;
    status: string;
    status_label?: string;
    moderation_notes?: string | null;
    submitted_for_review_at?: string | null;
    moderated_at?: string | null;
    moderator_name?: string | null;
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
    maxImageUploadBytes?: number;
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
    staff_permissions?: string[];
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
    seller_orders_count?: number;
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
    seller_orders?: Array<{
        id: number;
        seller_order_number: string;
        store_id: number;
        seller_name: string;
        is_official_store: boolean;
        status: string;
        status_label: string;
        total_formatted: string;
        shipping_carrier: string | null;
        tracking_number: string | null;
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
    }>;
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

export interface AdminSellerSummary {
    pending: number;
    under_review: number;
    approved: number;
    rejected: number;
    suspended: number;
}

export interface AdminSellerListRow {
    id: number;
    business_name: string;
    business_type: string;
    status: string;
    status_label: string;
    documents_count: number;
    applicant: {
        id: number;
        name: string;
        email: string;
    };
    store: {
        id: number;
        name: string;
        slug: string;
        status: string;
        status_label: string;
    } | null;
    submitted_at: string | null;
    submitted_at_diff: string;
}

export interface AdminSellerTransitionOption {
    value: string;
    label: string;
    requires_reason: boolean;
}

export interface AdminSellerDetail {
    id: number;
    business_name: string;
    business_type: string;
    contact_phone: string;
    business_address: string;
    tax_identifier: string | null;
    payout_notes: string | null;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    approved_at: string | null;
    suspended_at: string | null;
    reviewer_name: string | null;
    applicant: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        email_verified: boolean;
        joined_at: string | null;
    };
    store: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        contact_email: string | null;
        contact_phone: string | null;
        status: string;
        status_label: string;
    } | null;
    documents: Array<{
        id: number;
        document_type: string;
        document_type_label: string;
        original_filename: string;
        mime_type: string;
        size_bytes: number;
        created_at_diff: string;
    }>;
    status_history: Array<{
        id: number;
        from_status: string | null;
        to_status: string;
        to_status_value: string;
        reason: string | null;
        actor_name: string;
        created_at: string;
    }>;
}
