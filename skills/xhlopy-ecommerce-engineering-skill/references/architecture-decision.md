# XHlopy Architecture Decision Record: Hybrid Multi-Vendor Marketplace

- **Decision:** Adopt a hybrid, multi-vendor-ready marketplace architecture.
- **Status:** Confirmed by the project owner on 2026-11-21.
- **Scope:** Product architecture, authorization, seller onboarding, order model, database design, implementation planning.

## Decision summary

XHlopy will provide three distinct portals:
1. **Super Admin Portal** — governs the marketplace, seller verification/approval, platform configuration, moderation, financial oversight, staff permissions, and audit.
2. **Seller Portal** — allows each approved seller to manage only their own store, catalog, inventory, seller order fulfillment, and seller financial information.
3. **Customer Portal** — allows customers to browse the marketplace, maintain their account/cart, purchase products, and view their own orders, payments, and support cases.

Build the underlying system as multi-vendor-ready from the beginning. Seller registration may be available, but a seller must not publish products or transact as an active seller until the required verification and approval workflow is completed. The Super Admin (or specifically authorized staff) controls approval, rejection, suspension, and reactivation.

## Business rules

- Seller account states must be explicit, at minimum: `pending`, `under_review`, `approved`, `rejected`, `suspended`. Define any additional states only when requirements justify them.
- Seller onboarding captures only approved business/profile information and verification evidence. Define required documents and retention rules with the project owner; do not invent legal requirements.
- A pending, rejected, or suspended seller cannot perform active selling operations. Existing order obligations, records, disputes, and financial liabilities must remain accessible to authorized staff and handled according to approved policies.
- Each seller owns a store context. A seller may access only their own store, products, variants, inventory, stock movements, seller order groups, returns relevant to their sales, and seller earnings/payout records.
- Customers may buy products from more than one seller in one checkout, subject to the approved checkout and shipping rules.
- Represent a multi-seller purchase as a customer-facing parent order with seller-specific suborders/fulfillment groups (or an equivalent model that preserves the same isolation and lifecycle). Each seller can fulfill only their own suborder/items.
- Payment capture, platform fees/commissions, refunds, seller payable balances, payout holds, and payout execution must be represented separately and reconciled. Never equate GMV with platform revenue or seller earnings.
- Commission rates, fee calculation, payout schedule, refund allocation, shipping allocation, cancellation rules, and dispute policy are business decisions requiring explicit approval. Do not hardcode invented values.
- Super Admin can configure and oversee the marketplace. Staff permissions must be least-privilege and granular; not every staff member is a Super Admin.
- All privileged seller approval/suspension, product moderation, commission changes, refunds, payout actions, role changes, and sensitive settings changes must generate auditable events.
- Do not allow a seller to approve themselves, modify their role, alter platform commission rules, or access another seller's data.

## Portal boundaries

### Super Admin Portal
- Marketplace-wide dashboard and reporting.
- Seller applications, verification review, approval/rejection/suspension/reactivation.
- Customer and staff account administration.
- Global categories and marketplace catalog moderation.
- Marketplace-wide order oversight, disputes, refunds, payment reconciliation, commissions, and payouts.
- Promotions and platform content where globally managed.
- RBAC, audit logs, platform configuration, and operational monitoring.

### Seller Portal
- Store profile and seller onboarding status.
- Own products, variants, images, pricing, and inventory.
- Own seller order groups and fulfillment/shipping updates.
- Own reviews and customer inquiries, subject to privacy rules.
- Own sales reports, commission breakdowns, balances, and payout history.
- Seller-level promotions only where enabled by platform policy.

### Customer Portal
- Marketplace browsing, search, product detail, cart, and checkout.
- Own profile, addresses, wishlist, notifications, and payment/order history.
- Parent order view with seller-specific fulfillment progress.
- Returns/refunds, reviews, and support according to approved policies.

## Authorization and tenant isolation

- Treat `seller_id` / `store_id` as a tenant boundary for every seller-owned resource.
- Derive seller identity from the authenticated server-side principal and authorized store membership; never trust a seller/store ID supplied by the browser without checking ownership.
- Enforce ownership in backend policies/services/queries for reads, writes, exports, downloads, bulk actions, and background jobs.
- Apply deny-by-default authorization. Hiding navigation or buttons is not authorization.
- Test cross-seller IDOR/BOLA attempts for every seller-scoped endpoint and resource.
- Customer access is similarly restricted to their own carts, addresses, parent orders, payments, returns, and profile.
- Super Admin access must be permission-checked and audited; use step-up authentication for especially sensitive financial/security actions when supported.

## Data model guidance (conceptual; inspect existing schema first)

Likely entities include:
- `users`, `roles`, `permissions`, `user_role_assignments`
- `seller_profiles`, `seller_verification_documents`, `seller_status_history`
- `stores`, `store_memberships` (if multiple staff per seller are supported)
- `categories`, `products`, `product_variants`, `product_images`
- `inventories`, `inventory_movements`, `stock_reservations`
- `carts`, `cart_items`, `wishlists`
- `orders` (customer-facing parent order), `seller_orders` (seller fulfillment group), `order_items`, `order_status_history`
- `payments`, `payment_events`, `refunds`, `commission_rules`, `commission_entries`
- `seller_ledger_entries`, `payout_requests`, `payouts`, `payout_events`
- `shipments`, `shipment_events`, `returns`, `disputes`
- `reviews`, `notifications`, `audit_logs`

Do not create every table mechanically. Map these concepts onto the existing schema, validate requirements, define relationships/invariants, and produce an ERD and migration plan before schema changes.

## Order, inventory, and finance invariants

- Snapshot item title, SKU, variant, unit price, currency, and applicable discount/tax details on order items so historical orders remain stable.
- Every order item must be attributable to exactly one seller/store at purchase time.
- Parent order totals must reconcile with seller suborder totals and the approved allocation of discounts, shipping, taxes, and fees.
- Inventory reservation and deduction must be concurrency-safe and seller-scoped. Prevent overselling across simultaneous checkouts.
- Payment provider events and payout provider events must be idempotent and auditable.
- Use an append-only or otherwise tamper-evident financial ledger pattern for commission, seller payable, refund, adjustment, and payout entries where appropriate. Never silently overwrite historical financial records.
- A seller must not be able to mark a payment as paid or execute a payout by changing a client-side status.

## Launch posture

The architecture is multi-vendor-ready, but operational capabilities can be feature-gated. The owner may choose to launch with a limited seller cohort or only the platform's own store. Do not remove multi-vendor data boundaries to simplify launch. Do not enable open seller onboarding, automated payouts, or unapproved payment flows until their policies, controls, and tests are accepted.

## Required deliverables before implementation

1. Current-state repository and database audit.
2. Portal and role/permission matrix.
3. Seller onboarding state diagram and approval workflow.
4. Parent-order / seller-suborder lifecycle diagram.
5. Conceptual ERD and data ownership matrix.
6. Commission, refunds, payout, shipping, and dispute questions list for owner decisions.
7. Security threat model focused on cross-seller/customer data isolation and financial abuse.
8. Phased implementation plan with acceptance criteria and rollback strategy.
