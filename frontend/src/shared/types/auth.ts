export type User = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role?: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    role?: string | null;
    roleLabel?: string | null;
    canAccessAdmin?: boolean;
    canManageCatalog?: boolean;
    canManageOrders?: boolean;
    canManageInventory?: boolean;
    canReviewSellers?: boolean;
    canManageOperations?: boolean;
    canManageUsers?: boolean;
    isSeller?: boolean;
    isApprovedSeller?: boolean;
    hasMfaEnabled?: boolean;
    mfaRecommended?: boolean;
};
