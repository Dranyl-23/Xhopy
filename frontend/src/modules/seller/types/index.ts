import type { PaginationMeta } from '@modules/catalog';

export interface SellerStoreSummary {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    status: string;
    status_label: string;
}

export interface SellerStoreEditable {
    id: number;
    name: string;
    slug: string;
    description: string;
    contact_email: string;
    contact_phone: string;
    status: string;
    status_label: string;
    products_count: number;
}

export interface SellerDocumentRow {
    id: number;
    document_type: string;
    document_type_label: string;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    created_at_diff: string;
}

export interface SellerStatusHistoryRow {
    id: number;
    from_status: string | null;
    to_status: string;
    to_status_value: string;
    reason: string | null;
    actor_name: string;
    created_at: string;
}

export interface SellerOnboardingProfile {
    id: number;
    status: string;
    status_label: string;
    can_edit: boolean;
    is_approved: boolean;
    business_name: string;
    business_type: string;
    contact_phone: string;
    business_address: string;
    tax_identifier: string | null;
    payout_notes: string | null;
    rejection_reason: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    approved_at: string | null;
    suspended_at: string | null;
    reviewer_name: string | null;
    store: SellerStoreSummary | null;
    documents: SellerDocumentRow[];
    status_history: SellerStatusHistoryRow[];
}

export interface SellerSelectOption {
    value: string;
    label: string;
}

export interface SellerDashboardMetrics {
    total_products: number;
    active_products: number;
    pending_review_products: number;
    rejected_products: number;
    low_stock_products: number;
    order_lines_count: number;
    seller_orders_count?: number;
    pending_fulfillment_orders?: number;
    shipped_orders?: number;
    delivered_orders?: number;
    confirmed_revenue_formatted?: string;
    available_balance_formatted?: string;
    on_hold_balance_formatted?: string;
}

export interface SellerProductRow {
    id: number;
    name: string;
    slug: string;
    sku: string;
    status: string;
    statusLabel: string;
    moderationNotes?: string | null;
    isFeatured: boolean;
    storeId: number | null;
    storeName: string;
    isOfficialStore: boolean;
    categoryName: string;
    brandName?: string | null;
    priceFormatted: string;
    stockQuantity: number;
    variantCount: number;
    imageUrl?: string | null;
    submittedForReviewAtDiff?: string | null;
}

export interface SellerProductIndexProps {
    store: {
        id: number;
        name: string;
        slug: string;
    };
    products: SellerProductRow[];
    pagination: PaginationMeta;
    filters: {
        q: string;
        status: string;
    };
    stats: {
        totalProducts: number;
        activeProducts: number;
        pendingReviewProducts: number;
        rejectedProducts: number;
        draftProducts: number;
    };
}

export interface SellerVariantItem {
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

export interface SellerImageItem {
    id: number;
    variant_id: number | null;
    url: string;
    alt_text: string;
    is_primary: boolean;
}

export interface SellerProductEditable {
    id: number;
    category_id: number;
    brand_id: number | null;
    store_id: number | null;
    store_name: string;
    status: string;
    status_label: string;
    moderation_notes: string | null;
    submitted_for_review_at: string | null;
    moderated_at: string | null;
    moderator_name: string | null;
    name: string;
    slug: string;
    sku: string;
    short_description: string;
    description: string;
    currency: string;
    base_price: string;
    compare_at_price: string;
    stock_quantity: number;
    specifications: Record<string, string>;
    variants: SellerVariantItem[];
    images: SellerImageItem[];
}

export interface SellerCatalogOption {
    id: number;
    name: string;
    slug?: string;
    is_active?: boolean;
}

export interface SellerProductFormProps {
    store: {
        id: number;
        name: string;
        slug: string;
    };
    product: SellerProductEditable | null;
    categories: SellerCatalogOption[];
    brands: SellerCatalogOption[];
    statuses: SellerSelectOption[];
    maxImageUploadBytes?: number;
}

export interface SellerInventorySkuRow {
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

export interface SellerStockMovementRow {
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

export interface SellerOrderListRow {
    id: number;
    seller_order_number: string;
    parent_order_number: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    recipient_name: string;
    destination_city: string;
    items_count: number;
    total_formatted: string;
    shipping_carrier: string | null;
    tracking_number: string | null;
    placed_at_diff: string;
    next_seller_status: string | null;
    next_seller_status_label: string | null;
}

export interface SellerOrdersIndexProps {
    store: {
        id: number;
        name: string;
        slug: string;
    };
    sellerOrders: {
        data: SellerOrderListRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    statuses: SellerSelectOption[];
    filters: {
        q: string;
        status: string;
    };
    stats: {
        totalOrders: number;
        toFulfillCount: number;
        shippedCount: number;
        deliveredCount: number;
        confirmedRevenueFormatted: string;
    };
}

export interface SellerOrderDetail {
    id: number;
    seller_order_number: string;
    parent_order_number: string;
    seller_name: string;
    is_official_store: boolean;
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
    order_notes: string | null;
    shipping_carrier: string | null;
    tracking_number: string | null;
    placed_at_diff: string;
    paid_at_diff: string | null;
    shipped_at_diff: string | null;
    delivered_at_diff: string | null;
    cancelled_at_diff: string | null;
    refunded_at_diff: string | null;
    allowed_transitions: SellerSelectOption[];
    items: Array<{
        id: number;
        product_name: string;
        variant_title: string | null;
        sku: string;
        quantity: number;
        unit_price_formatted: string;
        line_total_formatted: string;
    }>;
    status_history: Array<{
        id: number;
        to_status_label: string;
        actor_name: string;
        reason: string | null;
        created_at_diff: string;
    }>;
}

export interface SellerFinanceBalances {
    inFulfillmentCentavos: number;
    inFulfillmentFormatted: string;
    onHoldCentavos: number;
    onHoldFormatted: string;
    maturedCentavos: number;
    maturedFormatted: string;
    availableCentavos: number;
    availableFormatted: string;
    pendingPayoutCentavos: number;
    pendingPayoutFormatted: string;
    paidOutCentavos: number;
    paidOutFormatted: string;
    grossConfirmedCentavos: number;
    grossConfirmedFormatted: string;
    commissionChargedCentavos: number;
    commissionChargedFormatted: string;
    minimumPayoutCentavos: number;
    minimumPayoutFormatted: string;
    commissionRateBps: number;
    commissionRatePercentFormatted: string;
    holdDays: number;
}

export interface SellerPayoutRow {
    id: number;
    payout_number: string;
    status: string;
    status_label: string;
    amount_formatted: string;
    payout_method: string;
    payout_destination: string;
    external_reference: string | null;
    review_notes: string | null;
    requested_at_diff: string;
    paid_at_diff: string | null;
}

export interface SellerLedgerEntryRow {
    id: number;
    entry_type: string;
    entry_type_label: string;
    amount_centavos: number;
    amount_formatted: string;
    description: string;
    created_at_diff: string;
}

export interface SellerCommissionSnapshotRow {
    id: number;
    seller_order_id: number;
    seller_order_number: string;
    suborder_status_label: string;
    gross_formatted: string;
    rate_percent: string;
    commission_formatted: string;
    net_formatted: string;
    status: string;
    hold_until_label: string | null;
    recorded_at_diff: string;
}

export interface SellerFinancePageProps {
    store: {
        id: number;
        name: string;
        slug: string;
        is_official: boolean;
        payout_notes_default: string;
    };
    balances: SellerFinanceBalances;
    payouts: SellerPayoutRow[];
    ledgerEntries: SellerLedgerEntryRow[];
    commissionSnapshots: SellerCommissionSnapshotRow[];
}
