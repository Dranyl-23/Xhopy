export interface AnnouncementBanner {
    id: string;
    message: string;
    linkText?: string | null;
    linkHref?: string | null;
}

export interface CategorySummary {
    id: number | string;
    name: string;
    slug: string;
    description?: string | null;
    productCount?: number | null;
    imageUrl?: string | null;
}

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

export interface HeroCampaign {
    badgeText?: string | null;
    eyebrow?: string | null;
    title: string;
    subtitle: string;
    primaryCtaLabel: string;
    primaryCtaHref: string;
    secondaryCtaLabel?: string | null;
    secondaryCtaHref?: string | null;
}

export interface DailyInterestItem {
    id: string;
    tag: string;
    title: string;
    summary: string;
    ctaLabel: string;
    href: string;
    imageUrl?: string | null;
    imageAlt?: string | null;
}

export interface DailyInterestSection {
    featured: DailyInterestItem;
    tiles: DailyInterestItem[];
}

export interface CartSummary {
    itemCount: number;
    subtotalFormatted?: string | null;
}

export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

export interface StorefrontHomeProps {
    announcement?: AnnouncementBanner | null;
    hero?: HeroCampaign | null;
    categories: CategorySummary[];
    topProducts: ProductSummary[];
    dailyInterest?: DailyInterestSection | null;
    cartSummary: CartSummary;
    searchQuery?: string;
    selectedCategory?: string | null;
    isDemoPreview?: boolean;
}
