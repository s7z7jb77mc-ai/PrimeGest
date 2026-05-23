import '../css/app.css'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import type { DefineComponent } from 'vue'
import { createApp, h } from 'vue'
import { ZiggyVue } from 'ziggy-js'
import { createPinia } from 'pinia'
import { initializeTheme } from './composables/useAppearance'
import { useOfflineStore } from './stores/useOfflineStore'

const appName = import.meta.env.VITE_APP_NAME || 'PrimeGest'
const pinia = createPinia()

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)

        // Initialisation du store Offline
        const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window
        const offlineStore = useOfflineStore()
        offlineStore.setTauri(isTauri)
        offlineStore.initNetworkListeners()

        app.mount(el)

        if (isTauri) {
            // Tauri : sync initial + périodique pour peupler la base SQLite locale dès le démarrage
            import('./composables/useOfflineQueue').then(async ({ useOfflineQueue }) => {
                const { syncPending } = useOfflineQueue()
                if (offlineStore.isOnline) syncPending()
                setInterval(() => { if (offlineStore.isOnline) syncPending() }, 5 * 60 * 1000)
            })
        } else {
            // PWA web : pull initial + auto-sync via SyncManager
            import('./composables/useSyncManager').then(({ useSyncManager }) => {
                const { startAutoSync } = useSyncManager()
                startAutoSync()
            })
        }

        // En Tauri hors-ligne : supprimer la modale d'erreur Inertia (le SW sert le cache)
        if (isTauri) {
            import('@inertiajs/vue3').then(({ router }) => {
                router.on('exception', (event) => {
                    if (!offlineStore.isOnline) {
                        event.preventDefault()
                    }
                })
            })
        }

        // Pré-cache toutes les routes Inertia dès que l'user est en ligne (Tauri et PWA)
        // Garantit la navigation offline même pour les pages jamais visitées
        if ('serviceWorker' in navigator && navigator.onLine) {
            const version = (props as any).initialPage?.version || ''
            navigator.serviceWorker.ready.then((reg) => {
                reg.active?.postMessage({
                    type: 'PRECACHE_INERTIA',
                    routes: [
                        '/dashboard', '/produits', '/mouvement-stocks',
                        '/caisse', '/journals', '/tiers', '/creances-dettes',
                        '/rapports', '/transferts', '/succursales', '/entreprises',
                    ],
                    version,
                })
            })
        }
    },

    progress: { color: '#4B5563' },
}) // <--- Correction : Ajout de la parenthèse de fermeture ici

// --- Post-initialisation ---

// Initialisation du thème (en dehors du setup Inertia pour éviter le flash blanc)
initializeTheme()

