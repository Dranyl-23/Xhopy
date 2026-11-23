import type { CartSummary } from '@modules/cart';

export interface CategorySummary {
    id: number | string;
    name: string;
    slug: string;
    description?: string | null;
    productCount?: number | null;
    imageUrl?: string | null;
}

export interface BrandSummary {
    id: number | string;
    name: string;
    slug: string;
    description?: string | null;
}

export interface StoreSummary {
    id: number;
    name: string;
    slug: string;
    url: string;
    isOfficial: boolean;
    logoUrl: string | null;
    description: string | null;
    status: string;
    statusLabel: string;
    productCount: number;
}

export interface ProductSummary {
    id: number | string;
    name: string;
    slug: string;
    sku?: string | null;
    url?: string | null;
    categoryName?: string | null;
    categorySlug?: string | null;
    brandName?: string | null;
    brandSlug?: string | null;
    storeId?: number | null;
    storeSlug?: string | null;
    storeUrl?: string | null;
    isOfficialStore?: boolean;
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
    hasVariants?: boolean;
}

export interface ProductImageItem {
    id: number;
    variantId?: number | null;
    url: string;
    alt: string;
    isPrimary: boolean;
}

export interface ProductVariantItem {
    id: number;
    title: string;
    sku: string;
    unitPrice: number;
    priceFormatted: string;
    originalPrice?: number | null;
    originalPriceFormatted?: string | null;
    discountLabel?: string | null;
    currency: string;
    stockQuantity: number;
    inStock: boolean;
    stockLabel: string;
    options: Record<string, string>;
    isDefault: boolean;
}

export interface ProductDetail extends ProductSummary {
    shortDescription?: string | null;
    description?: string | null;
    stockQuantity: number;
    specifications: Record<string, string>;
    images: ProductImageItem[];
    variants: ProductVariantItem[];
}

export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

export interface SortOption {
    value: string;
    label: string;
}

export interface CatalogFilters {
    q: string;
    category: string | null;
    brand: string | null;
    store?: string | null;
    min_price: string;
    max_price: string;
    in_stock: boolean;
    sort: string;
    per_page: number;
}

export interface CatalogIndexProps {
    products: ProductSummary[];
    pagination: PaginationMeta;
    categories: CategorySummary[];
    brands: BrandSummary[];
    stores?: StoreSummary[];
    sortOptions: SortOption[];
    filters: CatalogFilters;
    cartSummary: CartSummary;
}

export interface StorefrontStoresIndexProps {
    stores: StoreSummary[];
    categories: CategorySummary[];
    filters: {
        q: string;
    };
    cartSummary: CartSummary;
}

export interface StorefrontStoreShowProps {
    store: StoreSummary;
    products: ProductSummary[];
    pagination: PaginationMeta;
    categories: CategorySummary[];
    brands: BrandSummary[];
    sortOptions: SortOption[];
    filters: CatalogFilters;
    cartSummary: CartSummary;
}

export interface ProductDetailProps {
    product: ProductDetail;
    relatedProducts: ProductSummary[];
    categories: CategorySummary[];
    cartSummary: CartSummary;
}
