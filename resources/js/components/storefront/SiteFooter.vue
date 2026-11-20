<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, ShieldCheck, Truck } from '@lucide/vue';
import { home, login, register } from '@/routes';

const currentYear = new Date().getFullYear();
</script>

<template>
    <footer class="border-t border-border bg-charcoal text-charcoal-foreground">
        <!-- Trust & Service Guarantees (Accurate engineering statements only) -->
        <div class="border-b border-white/10">
            <div
                class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-8 sm:grid-cols-3 sm:px-6 lg:px-8"
            >
                <div class="flex items-start gap-3.5">
                    <span
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/20 text-primary-foreground"
                    >
                        <ShieldCheck class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold">
                            Authoritative Pricing &amp; Inventory
                        </h3>
                        <p class="mt-0.5 text-xs text-charcoal-foreground/75">
                            All product availability, discounts, and order
                            totals are verified server-side at checkout.
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <span
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/20 text-primary-foreground"
                    >
                        <Lock class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold">
                            Protected Account &amp; Checkout
                        </h3>
                        <p class="mt-0.5 text-xs text-charcoal-foreground/75">
                            Supports two-factor authentication, passkeys, and
                            cryptographically verified payment webhooks.
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <span
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/20 text-primary-foreground"
                    >
                        <Truck class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold">
                            Transparent Order Tracking
                        </h3>
                        <p class="mt-0.5 text-xs text-charcoal-foreground/75">
                            Real-time status history from reservation and
                            payment confirmation through fulfillment.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Footer Navigation -->
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
                <div class="space-y-3 md:col-span-2">
                    <Link :href="home()" class="inline-flex items-center gap-2">
                        <span
                            class="inline-flex size-8 items-center justify-center rounded-lg bg-primary text-xs font-extrabold text-primary-foreground"
                        >
                            XH
                        </span>
                        <span class="text-lg font-extrabold tracking-tight">
                            XHopy
                        </span>
                    </Link>
                    <p class="max-w-sm text-sm text-charcoal-foreground/75">
                        Modern e-commerce storefront engineered for speed,
                        accessibility, and data integrity.
                    </p>
                </div>

                <div>
                    <h4 class="text-xs font-semibold tracking-wider uppercase">
                        Storefront
                    </h4>
                    <ul
                        class="mt-3 space-y-2 text-sm text-charcoal-foreground/75"
                    >
                        <li>
                            <Link
                                :href="home()"
                                class="hover:text-white focus-visible:underline focus-visible:outline-none"
                            >
                                Home &amp; Catalog
                            </Link>
                        </li>
                        <li>
                            <a
                                href="#top-products-heading"
                                class="hover:text-white focus-visible:underline focus-visible:outline-none"
                            >
                                Top Products
                            </a>
                        </li>
                        <li>
                            <a
                                href="#categories-heading"
                                class="hover:text-white focus-visible:underline focus-visible:outline-none"
                            >
                                Categories
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-xs font-semibold tracking-wider uppercase">
                        Customer Account
                    </h4>
                    <ul
                        class="mt-3 space-y-2 text-sm text-charcoal-foreground/75"
                    >
                        <li>
                            <Link
                                :href="login()"
                                class="hover:text-white focus-visible:underline focus-visible:outline-none"
                            >
                                Sign In
                            </Link>
                        </li>
                        <li>
                            <Link
                                :href="register()"
                                class="hover:text-white focus-visible:underline focus-visible:outline-none"
                            >
                                Create Account
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-6 text-xs text-charcoal-foreground/60 sm:flex-row"
            >
                <p>&copy; {{ currentYear }} XHopy. All rights reserved.</p>
                <p>Currency: PHP (& Philippine Peso)</p>
            </div>
        </div>
    </footer>
</template>
