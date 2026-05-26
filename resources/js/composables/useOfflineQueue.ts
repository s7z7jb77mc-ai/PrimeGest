import { db } from '@/db/primegest'
import { useOfflineStore } from '@/stores/useOfflineStore'

const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

// Map tableName → table Dexie pour le write-through local
const DEXIE_TABLE_MAP: Record<string, any> = {
    produits:        null, // initialisé lazily après import de db
    clients:         null,
    fournisseurs:    null,
    journals:        null,
    mouvement_stocks:null,
    caisses:         null,
    transferts:      null,
    succursales:     null,
}

function getDexieTable(tableName: string): any | null {
    const tableMap: Record<string, any> = {
        produits:         db.produits,
        clients:          db.clients,
        fournisseurs:     db.fournisseurs,
        journals:         db.journals,
        mouvement_stocks: db.mouvement_stocks,
        caisses:          db.caisses,
        transferts:       db.transferts,
        succursales:      db.succursales,
        stocks:           db.stocks,
    }
    return tableMap[tableName] ?? null
}

/**
 * Write-through : reflète immédiatement l'opération dans Dexie
 * pour que l'UI se rafraîchisse sans attendre la sync.
 */
async function writeThrough(
    tableName: string,
    recordId: string,
    operation: 'create' | 'update' | 'delete',
    payload: Record<string, any>,
): Promise<void> {
    const table = getDexieTable(tableName)
    if (!table) return
    try {
        if (operation === 'delete') {
            await table.update(recordId, { deleted_at: Math.floor(Date.now() / 1000) })
        } else {
            await table.put({
                ...payload,
                uuid:       recordId,
                updated_at: Math.floor(Date.now() / 1000),
                deleted_at: null,
            })
        }
        // Notifier les pages pour qu'elles relisent Dexie
        window.dispatchEvent(new CustomEvent('primegest:local-write', { detail: { tableName, recordId, operation } }))
    } catch (e) {
        console.warn('[OfflineQueue] write-through échoué:', e)
    }
}

export function useOfflineQueue() {
    const offlineStore = useOfflineStore()

    async function queueOperation(
        tableName: string,
        recordId: string,
        operation: 'create' | 'update' | 'delete',
        payload: Record<string, any>,
    ): Promise<void> {
        if (isTauri) {
            const { invoke } = await import('@tauri-apps/api/core')
            await invoke('queue_operation', { tableName, recordId, operation, payload })
        } else {
            await db.sync_queue.add({
                table_name:          tableName,
                record_id:           recordId,
                operation,
                payload:             JSON.stringify(payload),
                status:              'pending',
                client_sync_version: 0,
                created_at:          Date.now(),
            })
            // Refléter immédiatement dans Dexie pour une UI réactive offline
            await writeThrough(tableName, recordId, operation, payload)
        }
        offlineStore.incrementPending()
    }

    async function syncPending(): Promise<void> {
        if (offlineStore.isSyncing) return
        offlineStore.setSyncing(true)

        try {
            if (isTauri) {
                const token = await getTauriSyncToken()
                if (!token) {
                    offlineStore.setSyncError('Non authentifié — impossible de synchroniser')
                    return
                }
                const { invoke } = await import('@tauri-apps/api/core')
                const result = await invoke<{ synced: number; conflicts: number; errors: number }>('sync_push', {
                    apiUrl:   window.location.origin,
                    apiToken: token,
                    deviceId: getDeviceId(),
                })
                await invoke<number>('sync_pull', {
                    apiUrl:     window.location.origin,
                    apiToken:   token,
                    deviceId:   getDeviceId(),
                    lastSyncTs: offlineStore.lastSyncAt,
                })
                offlineStore.setSyncSuccess(result.synced, result.conflicts)
            } else {
                await syncBrowserQueue()
            }
        } catch (err: any) {
            offlineStore.setSyncError(err?.message ?? 'Erreur inconnue')
        } finally {
            offlineStore.setSyncing(false)
        }
    }

    async function syncBrowserQueue(): Promise<void> {
        const pending = await db.sync_queue.where('status').equals('pending').toArray()
        if (!pending.length) {
            offlineStore.setSyncSuccess(0, 0)
            return
        }

        const operations = pending.map(op => ({
            table_name:          op.table_name,
            record_id:           op.record_id,
            operation:           op.operation,
            payload:             typeof op.payload === 'string' ? JSON.parse(op.payload) : op.payload,
            client_sync_version: op.client_sync_version ?? 0,
        }))

        const res = await fetch('/api/sync/push', {
            method:   'POST',
            redirect: 'manual',
            headers:  {
                'Content-Type':     'application/json',
                // Bug fix : X-CSRF-TOKEN attend le token brut (meta tag), pas la valeur chiffrée du cookie
                'X-CSRF-TOKEN':     getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            body: JSON.stringify({ device_id: getDeviceId(), operations }),
        })

        if (res.type === 'opaqueredirect' || res.status === 302) {
            window.location.reload()
            return
        }
        if (res.status === 401) {
            offlineStore.setSyncError('Session expirée — reconnectez-vous')
            return
        }
        if (!res.ok) {
            offlineStore.setSyncError(`Erreur serveur ${res.status}`)
            return
        }

        const json = await res.json()
        const results: Array<{ record_id: string; status: string }> = json.results ?? []

        let synced = 0
        let conflicts = 0

        for (const result of results) {
            const op = pending.find(p => p.record_id === result.record_id)
            if (!op?.id) continue
            if (result.status === 'synced') {
                await db.sync_queue.update(op.id, { status: 'synced' })
                synced++
            } else if (result.status === 'conflict') {
                // Marquer quand même comme synced pour vider la queue — le serveur a appliqué LWW
                await db.sync_queue.update(op.id, { status: 'synced' })
                conflicts++
            }
        }

        offlineStore.setSyncSuccess(synced, conflicts)
    }

    return { queueOperation, syncPending }
}

/**
 * Récupère le token Sanctum pour la sync Tauri.
 */
async function getTauriSyncToken(): Promise<string> {
    const offlineStore = useOfflineStore()
    if (offlineStore.syncToken) return offlineStore.syncToken

    try {
        const res = await fetch('/api/auth/tauri-token', {
            method: 'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ device_id: getDeviceId() }),
        })
        if (!res.ok) return ''
        const data = await res.json()
        offlineStore.setSyncToken(data.token)
        return data.token
    } catch {
        return ''
    }
}

/**
 * Retourne le token CSRF brut depuis la balise meta.
 * X-CSRF-TOKEN attend le token de session brut, PAS la valeur chiffrée du cookie XSRF-TOKEN.
 */
function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

export function getDeviceId(): string {
    let id = localStorage.getItem('primegest_device_id')
    if (!id) {
        id = crypto.randomUUID()
        localStorage.setItem('primegest_device_id', id)
    }
    return id
}
