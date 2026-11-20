<script setup lang="ts">
import { ArrowRight, Sparkles } from '@lucide/vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import type { DailyInterestSection } from '@modules/storefront/types';

defineProps<{
    dailyInterest: DailyInterestSection;
}>();
</script>

<template>
    <section aria-labelledby="daily-interest-heading" class="space-y-6">
        <div
            class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end"
        >
            <div>
                <div
                    class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary uppercase"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    <span>Curated Highlights</span>
                </div>
                <h2
                    id="daily-interest-heading"
                    class="mt-1 text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                >
                    Daily Interest
                </h2>
            </div>
            <p class="text-sm text-muted-foreground">
                Featured editorial collections and active seasonal spotlights.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Featured Editorial Banner (7 columns on desktop) -->
            <article
                class="relative flex flex-col justify-between overflow-hidden rounded-2xl bg-charcoal p-6 text-charcoal-foreground sm:p-8 lg:col-span-7"
            >
                <div class="relative z-10 max-w-xl space-y-4">
                    <Badge
                        class="bg-primary text-primary-foreground hover:bg-primary/90"
                    >
                        {{ dailyInterest.featured.tag }}
                    </Badge>
                    <h3
                        class="text-2xl leading-tight font-bold tracking-tight sm:text-3xl"
                    >
                        {{ dailyInterest.featured.title }}
                    </h3>
                    <p
                        class="text-sm leading-relaxed text-charcoal-foreground/80 sm:text-base"
                    >
                        {{ dailyInterest.featured.summary }}
                    </p>
                </div>

                <div class="relative z-10 mt-8 pt-2">
                    <Button
                        as="a"
                        :href="dailyInterest.featured.href"
                        class="h-11 gap-2 rounded-full px-6 text-sm font-semibold"
                    >
                        <span>{{ dailyInterest.featured.ctaLabel }}</span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </article>

            <!-- Supporting Campaign Tiles (5 columns on desktop) -->
            <div class="flex flex-col gap-4 lg:col-span-5">
                <article
                    v-for="tile in dailyInterest.tiles"
                    :key="tile.id"
                    class="group flex flex-1 flex-col justify-between rounded-2xl border border-border bg-card p-5 transition-colors hover:border-primary/50"
                >
                    <div class="space-y-2">
                        <span
                            class="inline-block rounded-md bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand-foreground"
                        >
                            {{ tile.tag }}
                        </span>
                        <h3 class="text-base font-bold text-foreground">
                            {{ tile.title }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ tile.summary }}
                        </p>
                    </div>

                    <div class="mt-4 pt-2">
                        <a
                            :href="tile.href"
                            class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span>{{ tile.ctaLabel }}</span>
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
