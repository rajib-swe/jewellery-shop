import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
        vuetify({ autoImport: true }),
        VitePWA({
            // "prompt" keeps the running version alive until the user accepts an
            // update, so a mid-sale form is never reloaded from under them.
            registerType: 'prompt',
            injectRegister: null,
            // Never name this manifest.json: Laravel's @vite directive reads
            // public/build/manifest.json and a collision breaks asset resolution.
            manifestFilename: 'manifest.webmanifest',
            // The service worker must sit at the web root to claim scope "/".
            // Emitting into public/build would cap its scope at /build/ and it
            // would never see a single /app/** navigation.
            outDir: 'public',
            buildBase: '/',
            scope: '/',
            // laravel-vite-plugin sets Vite publicDir to false, so includeAssets
            // globs against the project root and emits dead /public/** URLs that
            // fail the whole precache install. Manifest icons are absolute and get
            // fetched by the browser directly, so they need no precache entry.
            includeAssets: [],
            includeManifestIcons: false,
            manifest: {
                id: '/',
                name: 'Jewellery Shop Operations',
                short_name: 'Jewellery',
                description: 'Gold rates, inventory, sales, pawns, and purchases for your shop.',
                lang: 'bn',
                dir: 'ltr',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                display_override: ['standalone', 'minimal-ui'],
                orientation: 'portrait',
                background_color: '#f8f5ef',
                theme_color: '#8a6a32',
                categories: ['business', 'finance', 'productivity'],
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
                    { src: '/icons/icon-maskable-192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' },
                    { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // Laravel serves the SPA shell, so there is no index.html to fall back to.
                navigateFallback: null,
                globPatterns: ['**/*.{js,css,woff2,svg,png,ico,webmanifest}'],
                globIgnores: [
                    '**/manifest.json',
                    '**/*.map',
                    // Legacy icon-font formats: 2.6 MB that no PWA browser requests.
                    '**/*.woff',
                    '**/*.ttf',
                    '**/*.eot',
                ],
                cleanupOutdatedCaches: true,
                skipWaiting: false,
                // Required so accepting an update fires `controlling` and the
                // prompt's handler can actually reload the open tab.
                clientsClaim: true,
                maximumFileSizeToCacheInBytes: 4 * 1024 * 1024,
                runtimeCaching: [
                    {
                        // Authenticated JSON must never be written to disk.
                        urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
                        handler: 'NetworkOnly',
                    },
                    {
                        // Only the SPA shell routes are cacheable. Document routes
                        // such as /sales/{id}/pdf stay on the network.
                        urlPattern: ({ url }) => url.pathname === '/'
                            || url.pathname === '/login'
                            || url.pathname.startsWith('/app/'),
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'app-shell',
                            networkTimeoutSeconds: 3,
                            cacheableResponse: { statuses: [200] },
                            expiration: { maxEntries: 12, maxAgeSeconds: 60 * 60 * 24 },
                        },
                    },
                    {
                        urlPattern: ({ url }) => url.origin === 'https://fonts.bunny.net',
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'app-fonts',
                            cacheableResponse: { statuses: [0, 200] },
                            expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
                        },
                    },
                ],
            },
            // Registering a service worker against the dev server breaks HMR.
            devOptions: { enabled: false },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
