import { fileURLToPath, URL } from 'node:url';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { VitePWA } from 'vite-plugin-pwa';

const ICON_PATTERN = /mdi-[a-z0-9]+(?:-[a-z0-9]+)*/g;

function collectSourceFiles(dir) {
    return readdirSync(dir).flatMap((entry) => {
        const full = join(dir, entry);

        if (statSync(full).isDirectory()) {
            return collectSourceFiles(full);
        }

        return /\.(vue|js)$/.test(entry) ? [full] : [];
    });
}

/**
 * resources/css/mdi-icons.css is a committed subset of the Material Design Icons
 * font. Without this check a newly added icon would silently render blank, since
 * the glyph is simply not in the subset.
 */
function mdiSubsetGuard() {
    return {
        name: 'mdi-subset-guard',
        buildStart() {
            const sourceDir = fileURLToPath(new URL('./resources/js', import.meta.url));
            const subsetFile = fileURLToPath(new URL('./resources/css/mdi-icons.css', import.meta.url));

            const used = new Set();
            for (const file of collectSourceFiles(sourceDir)) {
                // Drop import lines first: a path such as "../css/mdi-icons.css"
                // would otherwise read as an icon named "mdi-icons".
                const source = readFileSync(file, 'utf8').replace(/^import\s[^\n]*$/gm, '');

                for (const match of source.matchAll(ICON_PATTERN)) {
                    used.add(match[0]);
                }
            }

            const subset = readFileSync(subsetFile, 'utf8');
            const missing = [...used].filter((icon) => !subset.includes(`.${icon}::before`));

            if (missing.length > 0) {
                throw new Error(
                    `These icons are not in resources/css/mdi-icons.css and would render blank:\n`
                    + missing.map((icon) => `  ${icon}`).join('\n')
                    + '\nRun "npm run icons:subset" and commit the result.',
                );
            }
        },
    };
}

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
        mdiSubsetGuard(),
        VitePWA({
            // "prompt" keeps the running version alive until the user accepts an
            // update, so a mid-sale form is never reloaded from under them.
            registerType: 'prompt',
            injectRegister: null,
            // The service worker must sit at the web root to claim scope "/".
            // Emitting into public/build would cap its scope at /build/ and it
            // would never see a single /app/** navigation.
            outDir: 'public',
            buildBase: '/',
            scope: '/',
            // The manifest is a hand written file at public/manifest.webmanifest
            // rather than a generated one. Passing a `manifest` option makes
            // vite-plugin-pwa register a web-root copy as a precache entry that it
            // never writes, and a single 404 there fails the whole atomic precache
            // install, which silently leaves the service worker inactive.
            // Note the plugin still drops an unused placeholder at
            // public/build/manifest.webmanifest; it is unreferenced and precache-excluded.
            includeAssets: [],
            includeManifestIcons: false,
            pwaAssets: { disabled: true },
            workbox: {
                // Laravel serves the SPA shell, so there is no index.html to fall back to.
                navigateFallback: null,
                globPatterns: ['**/*.{js,css,woff2,svg,png,ico,webmanifest}'],
                globIgnores: [
                    '**/manifest.json',
                    // vite-plugin-pwa also registers a web-root copy of the
                    // manifest that it never writes, and one 404 fails the whole
                    // precache install. The browser fetches the manifest through
                    // its <link rel="manifest"> tag, so it needs no precache entry.
                    '**/manifest.webmanifest',
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
                        // Safety net for hashed assets the precache does not know
                        // about, which happens between a deploy and the user
                        // accepting the update prompt.
                        urlPattern: ({ url }) => url.pathname.startsWith('/build/assets/'),
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'app-assets',
                            cacheableResponse: { statuses: [0, 200] },
                            expiration: { maxEntries: 80, maxAgeSeconds: 60 * 60 * 24 * 30 },
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
