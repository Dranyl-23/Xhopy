import { existsSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

const phpBinary =
    process.env.PHP_BINARY ??
    (process.platform === 'win32' && existsSync('C:\\php85\\php.exe')
        ? '"C:\\php85\\php.exe"'
        : 'php');

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
            '@modules': fileURLToPath(
                new URL('./src/modules', import.meta.url),
            ),
            '@shared': fileURLToPath(new URL('./src/shared', import.meta.url)),
        },
    },
    plugins: lazyPlugins(() => [
        laravel({
            input: ['src/styles/app.css', 'src/app.ts'],
            publicDirectory: '../backend/public',
            ssrOutputDirectory: '../backend/bootstrap/ssr',
            refresh: [
                '../backend/app/**',
                '../backend/routes/**',
                '../backend/resources/views/**',
            ],
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            command: `${phpBinary} ../backend/artisan wayfinder:generate`,
            path: 'src',
            patterns: [
                '../backend/routes/**/*.php',
                '../backend/app/**/Http/**/*.php',
            ],
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'node_modules/**',
            'playwright-report/**',
            'test-results/**',
            'tailwind.config.js',
            'src/actions/**',
            'src/shared/components/ui/*',
            'src/routes/**',
            'src/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            'playwright-report/**',
            'test-results/**',
            'src/shared/components/ui/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'src/styles/app.css',
        },
    },
});
