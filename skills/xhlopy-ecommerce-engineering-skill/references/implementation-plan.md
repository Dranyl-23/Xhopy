# XHlopy Implementation Plan — Hybrid Multi-Vendor Marketplace

**Confirmed product decision:** Hybrid / multi-vendor ready with Super Admin approval. XHlopy has three distinct portals: Super Admin, Seller, and Customer. The system must support multi-vendor ownership from the start, while seller operations remain approval-gated.

This is a target roadmap, not a claim about the current repository. The coding agent MUST audit the existing implementation first, preserve working architecture, and obtain approval before substantial or destructive changes.

## Phase 0 — Discovery, current-state audit, and decisions
- Inspect repository, stack, routes, frontend pages, services, database schema/migrations, auth, storage, payment/shipping integrations, deployment, tests, and design references.
- Establish build/test baseline and distinguish existing failures from regressions.
- Map current user roles, seller/store concepts, catalog ownership, cart, order, inventory, payment, and admin capabilities.
- Produce current-state architecture map, data-flow map, security risk register, and technical debt list.
- Identify business decisions still required: seller verification evidence, commission calculation, payout timing/method, refund allocation, cancellation/return rules, shipping allocation, dispute handling, tax treatment, and whether sellers can have staff accounts.
- Deliver portal/permission matrix, seller lifecycle diagram, parent-order/seller-suborder lifecycle, conceptual ERD, and phased plan for approval.

## Phase 1 — Platform foundation and identity
- Preserve or establish the approved technology stack after repository inspection.
- Define shared identity with explicit Customer, Seller, Super Admin, and limited Admin Staff roles/permissions.
- Implement deny-by-default authorization, server-side policies, secure sessions, account recovery, and audit events.
- Separate portal layouts and route groups while sharing backend/domain services.
- Define seller/store tenancy boundaries and customer resource ownership.
- Tests: role escalation, unauthenticated access, cross-customer access, cross-seller access, and direct URL/API access.

## Phase 2 — Super Admin foundation and seller onboarding
- Build Super Admin dashboard shell and staff RBAC.
- Seller application/profile workflow with explicit states: pending, under_review, approved, rejected, suspended (extend only with approved requirements).
- Capture verification evidence based on owner-approved requirements; protect private documents and access.
- Super Admin review, approve, reject, request changes, suspend, and reactivate workflows.
- Record actor, timestamp, decision, reason, and status history for each privileged action.
- Gate seller selling capabilities until approval. Test that pending/rejected/suspended sellers cannot publish or sell.

## Phase 3 — Seller Portal and seller-owned catalog/inventory
- Seller dashboard with own-store metrics only.
- Store profile management subject to platform policy.
- Product, variant, image, pricing, and inventory management scoped to the authenticated seller/store.
- Product moderation/approval workflow if required by owner policy.
- Stock movement history, safe adjustments, and concurrency-safe reservations.
- Tests: cross-seller IDOR/BOLA, unauthorized product changes, stock race conditions, and seller suspension behavior.

## Phase 4 — Customer storefront and Customer Portal
- Responsive marketplace storefront, categories, search, filters, product details, and seller/store attribution.
- Customer registration/login, profile, addresses, wishlist, notifications, and order history.
- Cart supports items from multiple sellers, with clear seller grouping and availability rules.
- Server-authoritative price, promotion, shipping, tax, and stock calculations.
- Tests: customer data isolation, cart tampering, price tampering, unavailable products, and mobile/tablet/desktop usability.

## Phase 5 — Multi-vendor checkout and order lifecycle
- Create a customer-facing parent order and seller-specific suborders/fulfillment groups (or approved equivalent).
- Snapshot purchased item data and seller attribution.
- Define valid parent and seller-order state transitions, including partial fulfillment/cancellation where approved.
- Each seller can view/update only their own fulfillment group; Super Admin has permission-controlled oversight; customer sees consolidated order progress.
- Use transactions, idempotency, concurrency controls, and reconciliation for checkout/order creation.
- Tests: mixed-seller checkout, partial fulfillment, duplicate requests, stock rollback, parent/suborder total reconciliation, and unauthorized seller access.

## Phase 6 — Payments, commissions, refunds, and payouts
- Integrate payment gateway through an adapter; begin in sandbox/test mode.
- Verify signed webhooks and process events idempotently; never trust browser redirects for payment success.
- Implement separate payment, commission, seller payable/ledger, refund, and payout records.
- Configure commission rules only after owner approval; define effective dates and historical snapshots.
- Implement payout review/approval and reconciliation before considering automation.
- Implement refund allocation and dispute workflows only after approved policies.
- Tests: webhook replay, duplicate events, payment mismatch, partial refund, payout authorization, ledger reconciliation, and financial auditability.

## Phase 7 — Shipping, support, marketing, and marketplace operations
- Shipping/fulfillment integration or manual workflow based on approved requirements.
- Seller/customer notifications and order tracking.
- Reviews, moderation, support tickets, returns, and disputes.
- Platform-wide promotions and seller-level promotions with explicit eligibility/stacking rules.
- Marketplace analytics: distinguish GMV, gross/net sales, platform commission revenue, refunds, seller payable, and payouts.

## Phase 8 — Security, performance, and launch readiness
- Independent authorization review across all portals and endpoints.
- Threat-model review for seller impersonation, cross-tenant data exposure, privilege escalation, payment/webhook abuse, inventory race conditions, and payout fraud.
- Automated tests, dependency/security scans, performance and accessibility checks.
- Backup/restore test, monitoring, alerting, incident response, staging acceptance, deployment and rollback plan.
- Launch with seller onboarding/operations controlled by Super Admin; enable additional capabilities only after acceptance.

## Phase acceptance template
For every phase record:
- Scope and out-of-scope items.
- Owner-approved business rules and acceptance criteria.
- Affected portals, frontend, backend, database, and integrations.
- Data ownership and authorization tests.
- Migration, backup, and rollback plan.
- Automated/manual tests and actual results.
- Security/privacy risks and unresolved decisions.
- Demo evidence and explicit approval to proceed.

## Non-negotiable constraints
- Do not rewrite an existing working application solely to match the suggested stack.
- Do not run destructive migrations or alter production data without explicit approval, verified backup, and rollback plan.
- Do not invent commission percentages, payout schedules, seller KYC requirements, tax/shipping rules, or refund policies.
- Do not expose one seller's data to another seller, or one customer's private data to another customer.
- Do not claim production readiness based only on a successful UI build; verify business logic, authorization, financial reconciliation, and tests.
