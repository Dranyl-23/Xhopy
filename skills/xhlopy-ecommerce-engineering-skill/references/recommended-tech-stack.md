# XHlopy Recommended Technology Stack

## Status and precedence

This is the **recommended target stack for a new XHlopy implementation**, not a claim about the current repository. The coding agent MUST inspect the existing repository before applying it.

### Mandatory decision rule

1. Detect and document the current framework, language/runtime versions, database, package manager, auth, storage, hosting, tests, and integrations.
2. If the repository already has a functioning stack, preserve it by default. Do not rewrite or migrate it to this recommendation without explicit user approval and a migration plan.
3. If the repository is empty/new, or the user explicitly approves a stack migration, use the target stack below.
4. Verify currently supported stable versions and compatibility from official documentation before installation. Do not blindly install `latest` or invent version constraints.
5. Keep a single source of truth for dependency versions in lockfiles. Do not mix package managers.

## Target stack

| Layer                    | Default                                  | Notes                                                                                                     |
| ------------------------ | ---------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Backend framework        | Laravel 13                               | Modular monolith; use supported stable patch release                                                      |
| Backend language         | PHP 8.3+                                 | Use a currently supported PHP release compatible with the selected Laravel version                        |
| Frontend                 | Vue 3 + TypeScript                       | Composition API; strict type checking                                                                     |
| Server/client bridge     | Inertia.js 3                             | Single repository and Laravel routing; avoid a separate SPA/API deployment unless requirements justify it |
| Styling                  | Tailwind CSS                             | Centralized design tokens and responsive utilities                                                        |
| Component primitives     | shadcn-vue                               | Accessible, composable components; own the application-level design system                                |
| Database                 | MySQL 8.4 LTS                            | Relational source of truth; verify hosting compatibility and supported version                            |
| Cache / queue / sessions | Redis                                    | Introduce when deployment supports it; database-backed queue/cache may be used initially                  |
| Build tooling            | Vite                                     | Use the framework starter kit's supported configuration                                                   |
| Backend tests            | Pest (Laravel-compatible)                | Unit, feature, database, authorization, and integration tests                                             |
| Browser E2E              | Playwright                               | Critical storefront, admin, checkout, and responsive flows                                                |
| File/object storage      | S3-compatible object storage             | Private by default; signed URLs for protected assets                                                      |
| Payment gateway          | PayMongo adapter                         | Sandbox first; provider eligibility, activation, fees, and methods must be confirmed                      |
| Source control / CI      | Git + GitHub Actions (if GitHub is used) | Automated quality gates; no secrets in repository                                                         |
| Local environment        | Existing team standard; Docker optional  | Do not force Docker if the repo already has a stable local setup                                          |

## Architecture choice

Start with a **modular monolith**, not microservices.

Suggested Laravel module boundaries:

- Identity & Access
- Catalog
- Categories & Merchandising
- Search & Discovery
- Cart
- Checkout
- Orders
- Inventory
- Payments
- Promotions
- Customer Account
- Reviews & Wishlist (only if requirements include them)
- Admin & Audit
- Notifications
- Reporting

Keep domain logic in focused services/actions and policies. Controllers should validate/authorize, call application logic, and return responses. Use Laravel's framework conventions instead of inventing a second architecture.

Use Inertia pages for interactive storefront/admin pages. Keep server-side validation and authorization authoritative. Add dedicated JSON endpoints only where needed (e.g. asynchronous search, external integrations, mobile clients, or third-party API access).

## Frontend implementation standards

- Vue 3 Composition API and TypeScript.
- Strict TypeScript; avoid `any` unless justified and isolated.
- Use reusable, typed components: SiteHeader, SearchBox, CategoryCard, ProductCard, PriceDisplay, RatingDisplay, CartButton, QuantitySelector, Pagination, EmptyState, ErrorState, LoadingState, Footer.
- Keep product/catalog data separate from presentation components.
- Use Tailwind design tokens for XHlopy's approved red, white, charcoal, neutral palette.
- Use shadcn-vue as accessible primitives, not as an excuse for inconsistent styling.
- Avoid duplicated components and giant single-file pages.
- Use server-provided product prices, inventory, and permissions; never trust client values.
- Build responsive mobile-first layouts and test keyboard/focus behavior.

## Backend implementation standards

- Use Laravel routes, Form Requests, Policies/Gates, Eloquent, migrations, jobs, events/listeners, notifications, and service classes where appropriate.
- Use Form Requests for boundary validation and Policies/Gates for authorization.
- Use transactions for order creation, stock reservations/deductions, refunds, and payment state changes.
- Use queues for non-blocking email, image processing, notifications, and reconciliation jobs where justified.
- Use explicit order/payment state transitions and idempotency.
- Keep provider-specific payment logic behind a PaymentGateway interface/adapter.
- Never trust a browser redirect as proof of payment. Verify signed provider webhooks and reconcile payment state server-side.
- Do not store card numbers or CVV.

## Database defaults

- MySQL is the primary relational database.
- Use Laravel migrations and seeders. Never change an already-applied production migration; create a new migration.
- Use foreign keys, unique constraints, suitable indexes, decimal monetary fields, and explicit status values.
- Persist order item snapshots (name, SKU, unit price, quantity, discounts/tax as applicable) so old orders do not change when catalog data changes.
- Use inventory movement records where traceability is required.
- Use atomic stock updates / row locking to prevent overselling.
- Keep timestamps consistent; store UTC and display local time appropriately.
- Redis is an auxiliary cache/queue/session service, not the source of truth for orders, payments, or inventory.

## Payment integration: PayMongo

- Confirm the merchant account's business type, enabled payment methods, and eligibility before promising specific channels.
- Develop and test in PayMongo test mode first.
- Create/confirm payments server-side using the official current PayMongo API and documented workflow.
- Verify webhook signatures, event IDs, and replay/duplicate handling according to current provider documentation.
- Make webhook processing idempotent and persist provider references and processing outcomes.
- Handle pending, succeeded, failed, expired, and refunded states explicitly.
- Never log API keys or sensitive payment payloads.
- Do not hardcode API credentials; use environment/secret management.
- Do not assume a payment method is active simply because the provider supports it.

## Deployment guidance

Do not prescribe a hosting provider before checking project constraints, budget, region, operational capacity, and existing deployment. Deployment should support:

- PHP/Laravel runtime compatible with the chosen version
- MySQL with automated backups and restore procedures
- Redis if used
- Queue workers and scheduler/cron
- HTTPS, secure secret management, logs, monitoring, and health checks
- Object storage for uploads
- Staging environment separate from production

## Official documentation to verify before implementation

- Laravel releases: https://laravel.com/docs/releases
- Laravel starter kits: https://laravel.com/starter-kits
- Laravel frontend / Inertia: https://laravel.com/docs/frontend
- Vue: https://vuejs.org/guide/
- Inertia: https://inertiajs.com/
- Tailwind CSS: https://tailwindcss.com/docs
- shadcn-vue: https://www.shadcn-vue.com/
- MySQL: https://dev.mysql.com/doc/
- Redis: https://redis.io/docs/
- Pest: https://pestphp.com/docs
- Playwright: https://playwright.dev/docs/intro
- PayMongo: https://docs.paymongo.com/

Always verify current versions and integration instructions from these official sources at implementation time.
