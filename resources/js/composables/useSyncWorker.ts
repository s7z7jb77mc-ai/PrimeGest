import { onMounted, onUnmounted, watch } from 'vue'
import { useOfflineStore } from '../stores/useOfflineStore'
import { usePage } from '@inertiajs/vue3'

// Interval de sync en ms — 30 secondes fixe pour tout le monde
const SYNC_INTERVAL_MS = 30_000

// Récupère le token Sanctum stocké en localStorage (mis à jour au login)
function getApiToken(): string {
    return localStorage.getItem('api_token') ?? ''
}

function getApiUrl(): string {
    return (window as any).__APP_URL__ ?? import.meta.env.VITE_APP_URL ?? ''
}

function getDeviceId(): string {
    let id = localStorage.getItem('device_id')
    if (!id) {
        id = crypto.randomUUID()
        localStorage.setItem('device_id', id)
    }
    return id
}

export function useSyncWorker() {
    const offlineStore = useOfflineStore()
    const page         = usePage()
    let   intervalId: ReturnType<typeof setInterval> | null = null

    // ── Invoke Tauri ou no-op si web ─────────────────────────────────────────

    async function tauriInvoke<T>(cmd: string, args: Record<string, unknown> = {}): Promise<T> {
        if (!offlineStore.isTauri) {
            throw new Error('Tauri non disponible')
        }
        const { invoke } = await import('@tauri-apps/api/core')
        return invoke<T>(cmd, args)
    }

    // ── Rafraîchir le compteur pending ───────────────────────────────────────

    async function refreshStatus() {
        if (!offlineStore.isTauri) return
        try {
            const status = await tauriInvoke<{
                pending_count: number
                last_sync_ts:  number
                is_online:     boolean
            }>('get_sync_status')
            offlineStore.setPendingCount(status.pending_count)
        } catch (e) {
            // silencieux — pas critique
        }
    }

    // ── Push + Pull ──────────────────────────────────────────────────────────

    async function runSync() {
        if (!offlineStore.isTauri)    return
        if (!offlineStore.isOnline)   return
        if (offlineStore.isSyncing)   return

        const token = getApiToken()
        if (!token) return

        offlineStore.setSyncing(true)

        try {
            // 1. Push les mutations locales
            const pushResult = await tauriInvoke<{
                synced:    number
                conflicts: number
                errors:    number
            }>('sync_push', {
                apiUrl:   getApiUrl(),
                apiToken: token,
                deviceId: getDeviceId(),
            })

            // 2. Pull le delta serveur
            const pulled = await tauriInvoke<number>('sync_pull', {
                apiUrl:      getApiUrl(),
                apiToken:    token,
                deviceId:    getDeviceId(),
                lastSyncTs:  offlineStore.lastSyncAt,
            })

            offlineStore.setSyncSuccess(pushResult.synced, pushResult.conflicts)

            if (pushResult.conflicts > 0) {
                console.warn(`[Sync] ${pushResult.conflicts} conflit(s) détecté(s)`)
            }

            if (pulled > 0) {
                // Émettre un event pour que les pages Vue rechargent leurs données
                window.dispatchEvent(new CustomEvent('primegest:sync-pulled', {
                    detail: { count: pulled }
                }))
            }

        } catch (e: any) {
            offlineStore.setSyncError(e?.message ?? 'Erreur inconnue')
        }
    }

    // ── Ajouter une opération à la queue locale ───────────────────────────────

    async function queueOperation(
        tableName: string,
        recordId:  string,
        operation: 'create' | 'update' | 'delete',
        payload:   Record<string, unknown>,
    ): Promise<void> {
        if (!offlineStore.isTauri) return

        await tauriInvoke('queue_operation', {
            tableName,
            recordId,
            operation,
            payload,
        })

        offlineStore.incrementPending()

        // Tenter une sync immédiate si on est en ligne
        if (offlineStore.isOnline) {
            runSync()
        }
    }

    // ── Initialiser le token Sanctum depuis la session web active ────────────

    async function initTauriToken(): Promise<void> {
        if (!offlineStore.isTauri) return
        if (localStorage.getItem('api_token')) return

        const deviceId = getDeviceId()
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

        try {
            const res = await fetch('/api/auth/tauri-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ device_id: deviceId }),
                credentials: 'include',
            })
            if (res.ok) {
                const data = await res.json()
                if (data.token) localStorage.setItem('api_token', data.token)
            }
        } catch {
            // sera retenté au prochain montage
        }
    }

    // ── Démarrer le polling ──────────────────────────────────────────────────

    function startPolling() {
        if (intervalId) return
        refreshStatus()
        intervalId = setInterval(() => {
            if (offlineStore.isOnline) runSync()
        }, SYNC_INTERVAL_MS)
    }

    function stopPolling() {
        if (intervalId) {
            clearInterval(intervalId)
            intervalId = null
        }
    }

    // ── Sync immédiate quand on revient en ligne ─────────────────────────────

    function onBackOnline() {
        runSync()
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    onMounted(() => {
        if (!offlineStore.isTauri) return
        initTauriToken().then(() => {
            startPolling()
            runSync() // Sync immédiate pour peupler la DB locale dès le premier login
        })
        window.addEventListener('online', onBackOnline)
    })

    onUnmounted(() => {
        stopPolling()
        window.removeEventListener('online', onBackOnline)
    })

    // Sync immédiate si on passe en ligne pendant que le composant est monté
    watch(() => offlineStore.isOnline, (online) => {
        if (online) runSync()
    })

    return {
        runSync,
        queueOperation,
        refreshStatus,
        startPolling,
        stopPolling,
        initTauriToken,
    }
}
