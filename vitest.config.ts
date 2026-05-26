import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'url'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: ['./tests/js/setup.ts'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/composables/useOfflineQueue.ts', 'resources/js/composables/useSyncManager.ts'],
        },
    },
})
