import { db } from '@/db/primegest'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { getDeviceId } from '@/composables/useOfflineQueue'

const TABLE_MAP: Record<string, any> = {
    produits:        db.produits,
    clients:         db.clients,
    fournisseurs:    db.fournisseurs,
    caisses:         db.caisses,
    journals:        db.journals,
    mouvement_stocks:db.mouvement_stocks,
    transferts:      db.transferts,
    succursales:     db.succursales,
}

function toUnix(val: string | number | null | undefined): number {
    if (!val) return 0
    if (typeof val === 'number') return val
    return Math.floor(new Date(val).getTime() / 1000)
}

function normalizePull(table: string, row: Record<string, any>): Record<string, any> {
    const { payload, updated_at_ts, sync_version, ...meta } = row
    return {
        ...(typeof payload === 'object' && payload !== null ? payload : {}),
        uuid: meta.uuid,
        updated_at: toUnix(updated_at_ts ?? meta.updated_at),
        deleted_at: meta.deleted_at ? toUnix(meta.deleted_at) : null,
    }
}

export function useSyncManager() {
    const offlineStore = useOfflineStore()

    /**
     * Pull : récupère le delta serveur depuis last_sync_at et l'upsert dans Dexie.
     * Déclenché au login, au retour online, toutes les 5 min si online.
     */
    async function pull(): Promise<void> {
        if (!offlineStore.isOnline) return

        try {
            const meta   = await db.sync_meta.get('_global')
            const since  = meta?.last_sync_at ?? 0
            const device = getDeviceId()

            const res = await fetch(`/api/sync/pull?since=${since}&device_id=${device}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            })

            // 401 en Tauri = session expirée → rediriger vers login
            // 401 en web = middleware SPA non prêt → ne pas rediriger (évite la boucle)
            if (res.status === 401) {
                const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window
                if (isTauri) window.location.href = '/login'
                return
            }
            if (!res.ok) return

            const json  = await res.json()
            const delta = json.delta ?? json.data ?? {}

            let count = 0

            await db.transaction('rw',
                [db.produits, db.clients, db.fournisseurs, db.caisses,
                 db.journals, db.mouvement_stocks, db.transferts,
                 db.succursales, db.sync_meta],
                async () => {
                    for (const [table, rows] of Object.entries(delta)) {
                        const store = TABLE_MAP[table]
                        if (!store || !Array.isArray(rows)) continue
                        for (const row of rows as Record<string, any>[]) {
                            if (!row.uuid) continue
                            await store.put(normalizePull(table, row))
                            count++
                        }
                    }

                    await db.sync_meta.put({
                        table_name:   '_global',
                        last_sync_at: Math.floor(Date.now() / 1000),
                    })
                }
            )

            if (count > 0) {
                window.dispatchEvent(new CustomEvent('primegest:sync-pulled', { detail: { count } }))
            }
        } catch (err) {
            console.warn('[SyncManager] pull échoué:', err)
        }
    }

    /**
     * Push + Pull combiné : pousse la queue puis tire le delta.
     * Appelé au retour online.
     */
    async function sync(): Promise<void> {
        const { useOfflineQueue } = await import('@/composables/useOfflineQueue')
        const { syncPending } = useOfflineQueue()
        await syncPending()
        await pull()
    }

    /**
     * Démarre l'auto-sync : pull au démarrage, re-pull toutes les 5 min, sync au retour online.
     */
    function startAutoSync(): void {
        // Pull initial
        pull()

        // Pull toutes les 5 min si en ligne
        setInterval(() => {
            if (offlineStore.isOnline) pull()
        }, 5 * 60 * 1000)

        // Sync complet au retour online
        window.addEventListener('primegest:online', () => sync())
    }

    return { pull, sync, startAutoSync }
}
