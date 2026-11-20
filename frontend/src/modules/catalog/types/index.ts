export interface ProductSummary {
    id: number | string;
    name: string;
    slug: string;
    sku?: string | null;
    categoryName?: string | null;
    sellerName?: string | null;
    unitPrice: number;
    priceFormatted: string;
    originalPrice?: number | null;
    originalPriceFormatted?: string | null;
    discountLabel?: string | null;
    currency: string;
    inStock: boolean;
    stockLabel: string;
    imageUrl?: string | null;
    imageAlt: string;
    ratingAverage?: number | null;
    ratingCount?: number | null;
}

export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}
