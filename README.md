# XHopy E-Commerce Platform

Production-oriented modular monolith for the **XHopy** e-commerce platform, built with **Laravel 13**, **PHP 8.3+**, **Vue 3 + TypeScript**, **Inertia.js 3**, **Tailwind CSS v4**, **shadcn-vue**, and **MySQL 8.4 LTS**.

All engineering work in this repository is governed by [`skills/xhlopy-ecommerce-engineering-skill/SKILL.md`](skills/xhlopy-ecommerce-engineering-skill/SKILL.md) and [`docs/adr/001-technology-stack-and-modular-monolith.md`](docs/adr/001-technology-stack-and-modular-monolith.md).

---

## Technology Stack

| Layer                     | Technology                                                             |
| ------------------------- | ---------------------------------------------------------------------- |
| Backend                   | Laravel 13 (`v13.43+`)                                                 |
| Language                  | PHP 8.3+ (PHP 8.4 / 8.5 supported)                                     |
| Frontend                  | Vue 3.5 + TypeScript (Strict mode)                                     |
| Server/Client Bridge      | Inertia.js 3 (`@inertiajs/vue3` + `@inertiajs/vite`)                   |
| Styling & Primitives      | Tailwind CSS v4 + `shadcn-vue` (`reka-ui`)                             |
| Database                  | MySQL 8.4 LTS (SQLite in-memory supported for fast isolated test runs) |
| Cache / Queues            | Redis (Database driver fallback for initial local setup)               |
| Build Tooling             | Vite (`vite-plus`) + Laravel Wayfinder                                 |
| Testing                   | Pest 5 (Unit, Feature, Architecture) + Playwright (E2E)                |
| Static Analysis & Linting | Larastan (Level 7), Laravel Pint, `vp check`, `vue-tsc`                |
| File Storage              | S3-compatible object storage (private by default)                      |
| Payments                  | PayMongo adapter (behind `PaymentGateway` contract)                    |

---

## Prerequisites

1. **PHP 8.3+** with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `sodium`, `tokenizer`, `xml`, `zip` (and `redis` when using Redis).
    - _Windows note (`C:\php85\php.ini`)_: ensure `extension=pdo_sqlite`, `extension=sqlite3`, `extension=sodium`, `extension=intl`, and `extension=gd` are uncommented, and `C:\php85` (or Herd PHP 8.4) precedes any older XAMPP PHP on `PATH`.
2. **Composer 2.x**
3. **Node.js 24 LTS** (see `.nvmrc`) and **npm** (do not mix package managers; `package-lock.json` is authoritative).
4. **MySQL 8.4 LTS** running locally (default `.env.example` port `3307` to coexist cleanly with legacy services).

---

## Local Setup

```bash
# 1. Install PHP dependencies
composer install

# 2. Install frontend dependencies (use npm only)
npm ci

# 3. Create local environment config and generate application key
cp .env.example .env
php artisan key:generate

# 4. Configure your MySQL 8.4 credentials in .env, then run migrations
php artisan migrate

# 5. Build frontend assets (or run `composer dev` for hot reload)
npm run build
```

---

## Quality Gates

Every change must pass all repository quality gates before handoff:

```bash
# Run full CI suite (Frontend format/lint + vue-tsc + Pint + Larastan + Pest)
composer ci:check

# Individual backend checks
composer lint:check          # Laravel Pint formatting check
composer types:check         # Larastan (PHPStan level 7)
php artisan test             # Full check + Pest test suite
./vendor/bin/pest            # Pest unit, feature, and architecture tests directly

# Individual frontend checks
npm run check                # Frontend formatting & lint check
npm run types:check          # Strict TypeScript check (vue-tsc --noEmit)
npm run build                # Production bundle build
npm run test:e2e             # Playwright browser E2E smoke tests
```

---

## Domain Structure

```text
app/
  Domain/
    Identity/      # Roles, permissions, account security
    Catalog/       # Categories, brands, products, variants, media
    Cart/          # Server-authoritative cart & item validation
    Checkout/      # Checkout orchestration, stock reservation, idempotency
    Orders/        # Order lifecycle, snapshots, status transition guard
    Inventory/     # Stock movements, atomic adjustments, reconciliation
    Payments/      # PaymentGateway contract, PayMongo adapter, webhook verifier
    Promotions/    # Discount rules, campaigns, Daily Interest modules
    Customer/      # Profiles, address book, wishlists, reviews
    Admin/         # Back-office operations & immutable audit logs
```
