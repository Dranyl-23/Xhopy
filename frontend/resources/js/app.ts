import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AuthLayout from '@modules/account/layouts/AuthLayout.vue';
import SettingsLayout from '@modules/account/layouts/SettingsLayout.vue';
import { initializeTheme } from '@shared/composables/useAppearance';
import AppLayout from '@shared/layouts/AppLayout.vue';
import { initializeFlashToast } from '@shared/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'XHopy';

type PageModule = DefineComponent | { default: DefineComponent };

const modulePages = import.meta.glob<PageModule>('./modules/*/pages/**/*.vue');

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const suffix = `/pages/${name}.vue`;
        const matchedPath = Object.keys(modulePages).find((path) =>
            path.endsWith(suffix),
        );

        if (!matchedPath) {
            throw new Error(`Inertia page module not found: ${name}`);
        }

        const loaded = await modulePages[matchedPath]();

        return 'default' in loaded ? loaded.default : loaded;
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('storefront/'):
            case name.startsWith('catalog/'):
            case name.startsWith('cart/'):
            case name.startsWith('checkout/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#B91C1C',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
