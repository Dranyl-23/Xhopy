<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.995 0 0);
            }

            html.dark {
                background-color: oklch(0.15 0.005 260);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @php
            $frontendRoot = str_replace('\\', '/', (string) realpath(base_path('../frontend')));
            $componentEntry = null;

            try {
                $resolvedFile = app('inertia.view-finder')->find($page['component']);
                $normalizedFile = str_replace('\\', '/', (string) realpath($resolvedFile));
                if ($frontendRoot !== '' && str_starts_with($normalizedFile, $frontendRoot.'/')) {
                    $candidate = substr($normalizedFile, strlen($frontendRoot) + 1);
                    if (str_starts_with($candidate, 'src/')) {
                        $componentEntry = $candidate;
                    }
                }
            } catch (\Throwable) {
                $componentEntry = null;
            }

            $viteEntries = array_values(array_filter([
                'src/styles/app.css',
                'src/app.ts',
                $componentEntry,
            ]));
        @endphp

        @vite($viteEntries)
        <x-inertia::head>
            <title>{{ config('app.name', 'XHopy') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>