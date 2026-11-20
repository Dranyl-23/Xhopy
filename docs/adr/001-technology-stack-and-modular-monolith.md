# ADR-001: Target Technology Stack and Modular Monolith Architecture

- **Status:** Approved (2026-11-20)
- **Deciders:** Project Owner & Engineering Agent
- **Governing Skill:** `Skills/xhlopy-ecommerce-engineering-skill/SKILL.md`

## Context

The initial workspace contained only the XHopy engineering skill specification (`SKILL.md` and reference standards) with no pre-existing application code or database schema. Per the skill's Technology Stack Policy:

> If the repository is empty/new, or the user explicitly approves a stack migration, use the target stack. Start with a modular monolith; do not introduce microservices without a demonstrated need.

Additionally, the product must launch as a **single-seller store** while preserving clean boundaries to evolve into a **multi-vendor marketplace** later without destructive schema rewrites.

## Decision

1. **Architecture & Directory Organization:** Modular Monolith inside a single repository, organized into explicit top-level **`/backend`** (Laravel 13) and **`/frontend`** (Vue 3 + TypeScript + Inertia 3) folders:
    - Backend bounded domain modules under `backend/app/Domain/`: `Identity`, `Catalog`, `Cart`, `Checkout`, `Orders`, `Inventory`, `Payments`, `Promotions`, `Customer`, `Admin`.
    - Frontend isolated domain modules under `frontend/resources/js/modules/`: `storefront`, `catalog`, `cart`, `checkout`, `account`, `admin`, backed by a strict `frontend/resources/js/shared/` layer and automated boundary checks (`scripts/check-module-boundaries.mjs`). Cross-module imports consume only public module barrels (`@modules/<domain>`), and `shared/` never imports from `modules/`.
2. **Backend Stack:**
    - **Framework:** Laravel 13 (`^13.17`, installed at `v13.43.0`) on PHP 8.3+ (local runtime PHP 8.5 / 8.4).
    - **Authentication:** Laravel Fortify (`^1.43`) + Passkeys (`^0.3`) + 2FA + Email Verification.
    - **Typed Routing:** Laravel Wayfinder (`^0.1.23`).
    - **Eloquent Strictness:** `Model::shouldBeStrict(! app()->isProduction())` enabled in `AppServiceProvider` to prevent N+1 lazy loading, silent mass-assignment drops, and missing attribute access.
3. **Frontend Stack:**
    - **UI & Bridge:** Vue 3.5 (Composition API + `<script setup lang="ts">`) + Inertia.js 3 (`@inertiajs/vue3` & `@inertiajs/vite`).
    - **Styling & Primitives:** Tailwind CSS v4 + `shadcn-vue` (`reka-ui`, `class-variance-authority`, `@lucide/vue`).
    - **Build & Checks:** Vite (`vite-plus`), strict `vue-tsc` type checking.
    - **Single Package Manager:** `npm` (`package-lock.json` is the single frontend lockfile).
4. **Data & Infrastructure:**
    - **Primary Database:** MySQL 8.4 LTS (relational source of truth; strict decimal money representation, snapshots on `order_items`, atomic stock updates, and movement ledgers).
    - **Cache & Queues:** Database-backed initially for local simplicity, migrating to Redis for non-blocking jobs, sessions, and caching.
    - **File Storage:** S3-compatible object storage (private by default, signed URLs for protected assets).
    - **Payments:** `PaymentGateway` interface with a PayMongo adapter (sandbox-first, HMAC-SHA256 verified `Paymongo-Signature` webhooks, idempotent event processing).
5. **Multi-Vendor Readiness:**
    - Core catalog, inventory, and order models will avoid hardcoded single-merchant assumptions and support a nullable/default `store_id` / `seller_id` boundary so future marketplace expansion is additive.
6. **Quality Gates:**
    - Laravel Pint (formatter), Larastan Level 7 (static analysis), `vp check` (frontend format/lint), `vue-tsc --noEmit` (strict TypeScript), Pest 5 (unit/feature/architecture tests), Playwright (browser E2E).

## Consequences

- Thin controllers validate via Form Requests, authorize via Policies/Gates, and delegate domain rules to `backend/app/Domain/<Module>` actions and services.
- All price, discount, tax, shipping, inventory, and payment state transitions remain strictly server-authoritative.
