import { onMounted } from 'vue'

const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

export function useUpdater() {
    if (!isTauri) return

    onMounted(async () => {
        try {
            const { check } = await import('@tauri-apps/plugin-updater')
            const { relaunch } = await import('@tauri-apps/plugin-process')

            const update = await check()
            if (update?.available) {
                await update.downloadAndInstall()
                await relaunch()
            }
        } catch {
            // Silencieux — pas d'internet ou serveur de mises à jour indisponible
        }
    })
}
