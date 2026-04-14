import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
    ],

    build: {
        // ✅ Code splitting — chaque page charge seulement ce dont elle a besoin
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vendor chunks séparés — mis en cache par le navigateur
                    'vendor-vue':     ['vue', '@inertiajs/vue3'],
                    'vendor-chart':   ['chart.js'],
                    // Lucide séparé mais tree-shaken grâce aux imports individuels
                    'vendor-lucide':  ['lucide-vue-next'],
                },
            },
        },

        // ✅ Taille minimale pour créer un chunk séparé (en bytes)
        chunkSizeWarningLimit: 600,

        // ✅ Minification agressive
        minify: 'esbuild',

        // ✅ Supprimer les console.log en production
        esbuildOptions: {
            drop: ['console', 'debugger'],
        },
    },

    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: {
            host: '127.0.0.1',
        },
    },

    // ✅ Optimisation des dépendances en dev
    optimizeDeps: {
        include: ['vue', '@inertiajs/vue3', 'chart.js'],
    },
});