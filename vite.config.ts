import { defineConfig } from 'vite'
import { fileURLToPath, URL } from 'url'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            // Stratégie : on garde notre sw.js custom, le plugin ne le régénère pas
            strategies: 'injectManifest',
            srcDir: 'public',
            filename: 'sw.js',

            // Blade gère l'enregistrement et les balises meta PWA
            injectRegister: null,
            manifest: false,

            // Pas d'injection de precache manifest (notre SW gère le cache dynamiquement)
            injectManifest: {
                injectionPoint: undefined,
                globPatterns: [],
            },

            // Dev : Laravel sert déjà public/sw.js statiquement, pas besoin du virtual SW
            devOptions: {
                enabled: false,
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            external: [
                '@tauri-apps/plugin-updater',
                '@tauri-apps/plugin-process',
                '@tauri-apps/api',
            ],
            output: {
                manualChunks: {
                    'vendor-vue':    ['vue', '@inertiajs/vue3', 'pinia'],
                    'vendor-chart':  ['chart.js'],
                    'vendor-lucide': ['lucide-vue-next'],
                },
            },
        },
        chunkSizeWarningLimit: 600,
        minify: 'esbuild',
    },
    esbuild: {
        drop: ['console', 'debugger'],
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: { host: '127.0.0.1' },
    },
    optimizeDeps: {
        include: ['vue', '@inertiajs/vue3', 'chart.js', 'pinia'],
    },
})
