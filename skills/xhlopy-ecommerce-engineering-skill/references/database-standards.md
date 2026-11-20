# Database and Data Integrity Standards

## Discovery first

- Identify database engine, ORM, migration tool, naming conventions, and existing schema before proposing changes.
- Inspect existing migrations and production compatibility. Do not assume a clean database.
- Prefer additive, backward-compatible migrations for deployed systems.

## Schema design

Potential entities (confirm requirements and existing schema before creating):

- users / customer_profiles / addresses
- roles / permissions / role_assignments
- categories / products / product_variants / product_images / product_attributes
- inventory_items / stock_movements / reservations
- carts / cart_items
- wishlists / wishlist_items
- orders / order_items / order_status_history
- payments / payment_events / refunds
- promotions / promotion_rules / campaign_content
- reviews
- audit_logs / notifications

Rules:

- Use primary keys and explicit foreign keys.
- Choose appropriate unique constraints (e.g. normalized email, SKU, provider event ID) based on actual business rules.
- Add indexes for measured/common lookup and join paths; avoid indiscriminate indexing.
- Use appropriate decimal precision for money and explicit currency.
- Define nullability and defaults intentionally.
- Use soft deletion only when retention/business requirements justify it.
- Store order-item product name/SKU/price snapshots so historical orders remain accurate after catalog changes.
- Record inventory movements, not just a mutable stock number, when auditability is required.
- Use explicit status enums/check constraints where supported and consistent with domain transitions.

## Integrity and transactions

- Enforce critical invariants in the database as well as application logic where possible.
- Use transactions for checkout/order creation, payment state updates, stock reservation/release, and related multi-row operations.
- Prevent overselling with atomic conditional updates, row locks, or equivalent concurrency-safe mechanisms.
- Ensure webhook/event processing is idempotent using unique provider event identifiers.
- Use optimistic or pessimistic locking where concurrent edits can conflict.
- Avoid N+1 queries; use appropriate joins/eager loading and inspect query plans for expensive queries.

## Migrations and backups

- Never edit an already-applied migration in a way that changes deployed history; add a new migration.
- Review generated SQL for destructive or locking operations.
- Provide rollback where safe; document irreversible operations.
- Back up before production migrations and verify restore procedures periodically.
- Test migrations against a copy/staging dataset representative of production.
- Never seed real customer data into development.
