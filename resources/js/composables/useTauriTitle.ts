import { watch } from 'vue'
import { useSyncStore } from '@/stores/useSyncStore'

const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

const STATUS_ICONS: Record<string, string> = {
    synced:  '✓',
    pending: '⏳',
    syncing: '↑',
    offline: '✗',
}

export function useTauriTitle() {
    if (!isTauri) return

    const sync = useSyncStore()

    watch(
        () => sync.status,
        async (status) => {
            const { getCurrentWindow } = await import('@tauri-apps/api/window')
            await getCurrentWindow().setTitle(`PrimeGest  ${STATUS_ICONS[status] ?? ''}`)
        },
        { immediate: true },
    )
}
