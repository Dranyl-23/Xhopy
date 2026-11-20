<script setup lang="ts">
import AppLogoIcon from '@shared/components/AppLogoIcon.vue';

type Props = {
    name?: string;
    subtitle?: string;
    size?: 'sm' | 'md' | 'lg';
    tone?: 'default' | 'dark';
    showSubtitleOnMobile?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    name: 'XHopy',
    subtitle: undefined,
    size: 'md',
    tone: 'default',
    showSubtitleOnMobile: false,
});

const badgeSizes: Record<NonNullable<Props['size']>, string> = {
    sm: 'size-7 rounded-md',
    md: 'size-8 rounded-lg',
    lg: 'size-9 rounded-lg',
};

const iconSizes: Record<NonNullable<Props['size']>, string> = {
    sm: 'size-4.5',
    md: 'size-5',
    lg: 'size-6',
};

const titleSizes: Record<NonNullable<Props['size']>, string> = {
    sm: 'text-base',
    md: 'text-lg',
    lg: 'text-lg sm:text-xl',
};

const prefixText = (brand: string): string =>
    brand.length >= 2 ? brand.slice(0, 2) : brand;

const suffixText = (brand: string): string =>
    brand.length >= 2 ? brand.slice(2) : '';
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <span
            :class="[
                'inline-flex shrink-0 items-center justify-center bg-gradient-to-br from-[#c90c22] to-[#960013] text-white shadow-xs ring-1 ring-black/5',
                badgeSizes[props.size],
            ]"
            aria-hidden="true"
        >
            <AppLogoIcon :class="iconSizes[props.size]" />
        </span>

        <span class="flex flex-col leading-none">
            <span
                :class="[
                    'font-extrabold tracking-tight',
                    titleSizes[props.size],
                    props.tone === 'dark'
                        ? 'text-charcoal-foreground'
                        : 'text-foreground',
                ]"
            >
                <span
                    :class="
                        props.tone === 'dark'
                            ? 'text-[#ff5766]'
                            : 'text-primary'
                    "
                    >{{ prefixText(props.name) }}</span
                ><span>{{ suffixText(props.name) }}</span>
            </span>
            <span
                v-if="props.subtitle"
                :class="[
                    'mt-0.5 text-[10px] font-medium tracking-wider uppercase',
                    props.showSubtitleOnMobile ? 'inline' : 'hidden sm:inline',
                    props.tone === 'dark'
                        ? 'text-charcoal-foreground/70'
                        : 'text-muted-foreground',
                ]"
            >
                {{ props.subtitle }}
            </span>
        </span>
    </span>
</template>
