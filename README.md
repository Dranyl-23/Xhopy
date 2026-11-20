# XHopy E-Commerce Platform

Production-oriented modular monolith for the **XHopy** e-commerce platform, built with **Laravel 13**, **PHP 8.3+**, **Vue 3 + TypeScript**, **Inertia.js 3**, **Tailwind CSS v4**, **shadcn-vue**, and **MySQL 8.4 LTS**.

All engineering work in this repository is governed by [`Skills/xhlopy-ecommerce-engineering-skill/SKILL.md`](Skills/xhlopy-ecommerce-engineering-skill/SKILL.md) and [`docs/adr/001-technology-stack-and-modular-monolith.md`](docs/adr/001-technology-stack-and-modular-monolith.md).

---

## Repository Directory Structure

The repository separates backend and frontend concerns into dedicated top-level folders while preserving seamless **Inertia.js 3** and **Laravel Wayfinder** integration:

```text
Noly/
├── backend/                     # Laravel 13 Backend (PHP 8.3+, Pest 5, Larastan, Pint)
│   ├── app/
│   │   ├── Domain/              # Modular monolith domain modules:
│   │   │   ├── Admin/
│   │   │   ├── Cart/
│   │   │   ├── Catalog/
│   │   │   ├── Checkout/
│   │   │   ├── Customer/
│   │   │   ├── Identity/
│   │   │   ├── Inventory/
│   │   │   ├── Orders/
│   │   │   ├── Payments/
│   │   │   └── Promotions/
│   │   ├── Http/Controllers/    # Thin Inertia & webhook controllers
│   │   ├── Models/
│   │   └── Providers/
│   ├── config/
│   ├── database/
│   ├── public/                  # Web root (Vite outputs compiled assets to public/build)
│   ├── resources/views/         # Root Inertia Blade view (app.blade.php)
│   ├── routes/
│   ├── tests/                   # Pest 5 Unit, Feature, and Architecture tests
│   ├── artisan
│   └── composer.json
├── frontend/                    # Vue 3 + TypeScript Frontend (Inertia 3, Tailwind v4, shadcn-vue)
│   ├── e2e/                     # Playwright E2E browser tests
│   ├── resources/
│   │   ├── css/app.css          # Tailwind CSS v4 & XHopy brand tokens
│   │   └── js/
│   │       ├── actions/         # Auto-generated Wayfinder controller actions
│   │       ├── components/      # Reusable UI (shadcn-vue) & Storefront components
│   │       ├── composables/
│   │       ├── layouts/         # StorefrontLayout, AppLayout, AuthLayout
│   │       ├── pages/           # Inertia Vue 3 pages
│   │       ├── routes/          # Auto-generated Wayfinder typed routes
│   │       ├── types/           # Strict TypeScript domain contracts
│   │       └── app.ts
│   ├── package.json
│   ├── playwright.config.ts
│   ├── tsconfig.json
│   └── vite.config.ts
├── docs/adr/                    # Architecture Decision Records
└── Skills/                      # XHopy E-Commerce Engineering Skill
```

---

## Local Setup

```bash
# 1. Backend setup (from /backend)
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# 2. Frontend setup (from /frontend)
cd ../frontend
npm ci
npm run build
```

---

## Quality Gates

```bash
# Full CI check from /backend (runs frontend lint + vue-tsc + Pint + Larastan + Pest)
cd backend && composer ci:check

# Individual Backend Checks (run inside /backend)
composer lint:check          # Laravel Pint formatting check
composer types:check         # Larastan (PHPStan level 7)
./vendor/bin/pest            # Pest 5 unit, feature, and architecture tests

# Individual Frontend Checks (run inside /frontend)
npm run check                # Frontend formatting & lint check (vp check)
npm run types:check          # Strict TypeScript check (vue-tsc --noEmit)
npm run build                # Production bundle build (outputs to ../backend/public/build)
npm run test:e2e             # Playwright browser E2E smoke tests
```