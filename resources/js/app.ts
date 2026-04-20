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
    },

    progress: { color: '#4B5563' },
}) // <--- Correction : Ajout de la parenthèse de fermeture ici

// --- Post-initialisation ---

// Initialisation du thème (en dehors du setup Inertia pour éviter le flash blanc)
initializeTheme()

// Enregistrement du Service Worker (Uniquement si pas sur Tauri)
const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

if (!isTauri && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((reg) => console.log('[SW] Enregistré:', reg.scope))
            .catch((err) => console.warn('[SW] Erreur:', err))
    })
}
