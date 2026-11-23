import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { AuthLayout, CustomerLayout } from '@modules/account';
import { AdminLayout } from '@modules/admin';
import { SellerLayout } from '@modules/seller';
import { initializeTheme } from '@shared/composables/useAppearance';
import PortalSettingsLayout from './layouts/PortalSettingsLayout.vue';
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
            case name === 'Error':
            case name.startsWith('storefront/'):
            case name.startsWith('catalog/'):
            case name.startsWith('stores/'):
            case name.startsWith('cart/'):
            case name.startsWith('checkout/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('seller/'):
                return SellerLayout;
            case name.startsWith('settings/'):
                return PortalSettingsLayout;
            default:
                return CustomerLayout;
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

initializeTheme();
initializeFlashToast();
