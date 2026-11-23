<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, Package, ShieldCheck, Store } from '@lucide/vue';
import InputError from '@shared/components/InputError.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { dashboard as sellerDashboard } from '@/routes/seller';
import { index as sellerProductsIndex } from '@/routes/seller/products';
import { update as updateSellerStore } from '@/routes/seller/store';
import type { SellerStoreEditable } from '@modules/seller/types';

const props = defineProps<{
    store: SellerStoreEditable;
    profile: {
        id: number;
        business_name: string;
        business_type: string;
        approved_at: string | null;
    };
}>();

const form = useForm({
    name: props.store.name,
    description: props.store.description,
    contact_email: props.store.contact_email,
    contact_phone: props.store.contact_phone,
});

function submit(): void {
    form.patch(updateSellerStore.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${store.name} — Store Profile`" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4 sm:p-6 lg:p-8">
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h1
                    class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                >
                    <Store class="size-6 text-primary" aria-hidden="true" />
                    <span>Store Profile &amp; Contact Info</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    Public storefront display name, contact channels, and brand
                    bio for your verified store.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="h-10 gap-1.5">
                    <Link :href="sellerProductsIndex()">
                        <Package class="size-4" aria-hidden="true" />
                        <span>Store Products ({{ store.products_count }})</span>
                    </Link>
                </Button>
                <Button as-child variant="outline" class="h-10">
                    <Link :href="sellerDashboard()">Dashboard</Link>
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-lg">
                            <Building2
                                class="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <span>{{ store.name }}</span>
                        </CardTitle>
                        <CardDescription>
                            Store slug:
                            <span class="font-mono text-foreground">{{
                                store.slug
                            }}</span>
                            · Legal entity: {{ profile.business_name }}
                        </CardDescription>
                    </div>
                    <Badge variant="default" class="gap-1">
                        <ShieldCheck class="size-3.5" aria-hidden="true" />
                        <span>{{ store.status_label }}</span>
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="store-name">Store Display Name</Label>
                            <Input
                                id="store-name"
                                v-model="form.name"
                                required
                                maxlength="120"
                                class="h-11"
                            />
                            <InputError :message="form.errors.name" />
                            <p class="text-xs text-muted-foreground">
                                Shown on product listings attributed to your
                                store across the XHopy catalog.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="store-email"
                                >Support Contact Email</Label
                            >
                            <Input
                                id="store-email"
                                v-model="form.contact_email"
                                type="email"
                                maxlength="160"
                                placeholder="support@yourstore.ph"
                                class="h-11"
                            />
                            <InputError :message="form.errors.contact_email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="store-phone"
                                >Support Contact Phone</Label
                            >
                            <Input
                                id="store-phone"
                                v-model="form.contact_phone"
                                type="tel"
                                maxlength="32"
                                placeholder="+63 917 000 0000"
                                class="h-11"
                            />
                            <InputError :message="form.errors.contact_phone" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="store-description"
                                >Store Description</Label
                            >
                            <textarea
                                id="store-description"
                                v-model="form.description"
                                rows="4"
                                maxlength="1000"
                                placeholder="Tell XHopy shoppers about your brand, product specialties, and shipping standards."
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-border pt-4">
                        <Button
                            type="submit"
                            class="h-11 min-w-[160px]"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : 'Save Store Profile'
                            }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
