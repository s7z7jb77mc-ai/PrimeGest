import { useOfflineStore } from '@/stores/useOfflineStore'

const DB_NAME    = 'primegest_offline'
const DB_VERSION = 1
const STORE_NAME = 'sync_queue'

// ── IndexedDB helpers ──────────────────────────────────────────────────────

function openDB(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION)
        req.onupgradeneeded = (e) => {
            const db = (e.target as IDBOpenDBRequest).result
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, {
                    keyPath: 'id',
                    autoIncrement: true,
                })
                store.createIndex('status', 'status', { unique: false })
            }
        }
        req.onsuccess = (e) => resolve((e.target as IDBOpenDBRequest).result)
        req.onerror   = (e) => reject((e.target as IDBOpenDBRequest).error)
    })
}

async function idbAdd(record: object): Promise<void> {
    const db    = await openDB()
    const tx    = db.transaction(STORE_NAME, 'readwrite')
    const store = tx.objectStore(STORE_NAME)
    store.add(record)
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve()
        tx.onerror    = () => reject(tx.error)
    })
}

async function idbGetPending(): Promise<any[]> {
    const db    = await openDB()
    const tx    = db.transaction(STORE_NAME, 'readonly')
    const store = tx.objectStore(STORE_NAME)
    const idx   = store.index('status')
    const req   = idx.getAll('pending')
    return new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result)
        req.onerror   = () => reject(req.error)
    })
}

async function idbMarkSynced(id: number): Promise<void> {
    const db    = await openDB()
    const tx    = db.transaction(STORE_NAME, 'readwrite')
    const store = tx.objectStore(STORE_NAME)
    const req   = store.get(id)
    req.onsuccess = () => {
        const record = req.result
        if (record) {
            record.status = 'synced'
            store.put(record)
        }
    }
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve()
        tx.onerror    = () => reject(tx.error)
    })
}

// ── API principale ─────────────────────────────────────────────────────────

export function useOfflineQueue() {
    const offlineStore = useOfflineStore()

    const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

    /**
     * Mettre une opération en file d'attente offline.
     * Utilisé quand on est hors ligne.
     */
    async function queueOperation(
        tableName : string,
        recordId  : string,
        operation : 'create' | 'update' | 'delete',
        payload   : Record<string, any>,
    ): Promise<void> {
        if (isTauri) {
            // Tauri : SQLite via Rust
            const { invoke } = await import('@tauri-apps/api/core')
            await invoke('queue_operation', {
                tableName,
                recordId,
                operation,
                payload,
            })
        } else {
            // Navigateur / PWA : IndexedDB
            await idbAdd({
                table_name : tableName,
                record_id  : recordId,
                operation,
                payload    : JSON.stringify(payload),
                status     : 'pending',
                created_at : Date.now(),
            })
        }
        offlineStore.incrementPending()
    }

    /**
     * Envoyer toutes les opérations en attente au serveur.
     * Appelé automatiquement quand on revient online.
     */
    async function syncPending(): Promise<void> {
        if (offlineStore.isSyncing) return
        offlineStore.setSyncing(true)

        try {
            if (isTauri) {
                // Tauri : cycle complet push + pull pour garder SQLite locale à jour.
                const { invoke } = await import('@tauri-apps/api/core')
                const result = await invoke<{ synced: number; conflicts: number; errors: number }>('sync_push', {
                    apiUrl   : window.location.origin,
                    apiToken : localStorage.getItem('api_token') ?? '',
                    deviceId : getDeviceId(),
                })

                const pulled = await invoke<number>('sync_pull', {
                    apiUrl     : window.location.origin,
                    apiToken   : localStorage.getItem('api_token') ?? '',
                    deviceId   : getDeviceId(),
                    lastSyncTs : offlineStore.lastSyncAt,
                })

                offlineStore.setSyncSuccess(result.synced, result.conflicts)

                if (pulled > 0) {
                    window.dispatchEvent(new CustomEvent('primegest:sync-pulled', {
                        detail: { count: pulled },
                    }))
                }
            } else {
                // Navigateur : envoi direct via fetch
                await syncBrowserQueue()
            }
        } catch (err: any) {
            offlineStore.setSyncError(err?.message ?? 'Erreur inconnue')
        } finally {
            offlineStore.setSyncing(false)
        }
    }

    /**
     * Sync navigateur : lit IndexedDB et envoie tout en un seul batch à /api/sync/push.
     */
    async function syncBrowserQueue(): Promise<void> {
        const pending = await idbGetPending()
        if (!pending.length) {
            offlineStore.setSyncSuccess(0, 0)
            return
        }

        const csrfToken = getCsrfToken()
        const deviceId  = getDeviceId()

        const operations = pending.map(op => ({
            table_name           : op.table_name,
            record_id            : op.record_id,
            operation            : op.operation,
            payload              : typeof op.payload === 'string' ? JSON.parse(op.payload) : op.payload,
            client_sync_version  : op.client_sync_version ?? 0,
        }))

        try {
            const res = await fetch('/api/sync/push', {
                method  : 'POST',
                redirect: 'manual',
                headers : {
                    'Content-Type'     : 'application/json',
                    'X-CSRF-TOKEN'     : csrfToken,
                    'X-Requested-With' : 'XMLHttpRequest',
                    'Accept'           : 'application/json',
                },
                body: JSON.stringify({ device_id: deviceId, operations }),
            })

            // Session expirée → recharger pour re-auth
            if (res.type === 'opaqueredirect' || res.status === 302) {
                window.location.reload()
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
                if (!op) continue
                if (result.status === 'synced') {
                    await idbMarkSynced(op.id)
                    synced++
                } else if (result.status === 'conflict') {
                    conflicts++
                }
            }

            offlineStore.setSyncSuccess(synced, conflicts)
        } catch (err: any) {
            offlineStore.setSyncError(err?.message ?? 'Erreur réseau')
        }
    }

    return { queueOperation, syncPending }
}

// ── Utilitaires ────────────────────────────────────────────────────────────

function getCsrfToken(): string {
    // Essayer d'abord le cookie XSRF-TOKEN (plus frais que la meta tag)
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    if (match) return decodeURIComponent(match[1])
    // Fallback sur la meta tag
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function getDeviceId(): string {
    let id = localStorage.getItem('primegest_device_id')
    if (!id) {
        id = crypto.randomUUID()
        localStorage.setItem('primegest_device_id', id)
    }
    return id
}
