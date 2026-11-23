export interface CartItemRow {
    id: number;
    productId: number;
    productVariantId: number | null;
    productName: string;
    productSlug: string;
    productUrl: string;
    variantTitle: string | null;
    sku: string;
    storeId: number | null;
    storeSlug: string | null;
    storeUrl: string | null;
    isOfficialStore: boolean;
    sellerName: string;
    unitPriceFormatted: string;
    quantity: number;
    maxQuantity: number;
    isAvailable: boolean;
    availabilityNote: string | null;
    lineTotalFormatted: string;
    imageUrl: string | null;
    imageAlt: string;
}

export interface CartSellerGroup {
    storeId: number | null;
    storeName: string;
    storeSlug: string | null;
    storeUrl: string | null;
    isOfficialStore: boolean;
    itemCount: number;
    subtotalCentavos: number;
    subtotalFormatted: string;
    hasStockIssues: boolean;
    items: CartItemRow[];
}

export interface CartSummary {
    itemCount: number;
    subtotalFormatted: string | null;
    items?: CartItemRow[];
}

export interface PresentedCart {
    itemCount: number;
    storeCount: number;
    currency: string;
    subtotalCentavos: number;
    subtotalFormatted: string;
    shippingFormatted: string;
    totalCentavos: number;
    totalFormatted: string;
    hasStockIssues: boolean;
    sellerGroups: CartSellerGroup[];
    items: CartItemRow[];
}

export interface CartPageProps {
    cart: PresentedCart;
    cartSummary: CartSummary;
    categories: Array<{
        id: number | string;
        name: string;
        slug: string;
        description: string | null;
        imageUrl: string | null;
        productCount: number;
    }>;
}
