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
