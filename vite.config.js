import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

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
    ],
    resolve: {
        alias: {
            'ziggy-js': '/vendor/tightenco/ziggy/dist/index.esm.js',
            '@': '/resources/js',
        },
    },
    build: {
        rollupOptions: {
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
