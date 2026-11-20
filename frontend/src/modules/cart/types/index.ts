export interface CartItemRow {
    id: number;
    productId: number;
    productVariantId: number | null;
    productName: string;
    productSlug: string;
    productUrl: string;
    variantTitle: string | null;
    sku: string;
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

export interface CartSummary {
    itemCount: number;
    subtotalFormatted: string | null;
    items?: CartItemRow[];
}

export interface PresentedCart {
    itemCount: number;
    currency: string;
    subtotalCentavos: number;
    subtotalFormatted: string;
    shippingFormatted: string;
    totalCentavos: number;
    totalFormatted: string;
    hasStockIssues: boolean;
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
