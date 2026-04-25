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
                // Tauri : sync via Rust
                const { invoke } = await import('@tauri-apps/api/core')
                const result = await invoke<{ synced: number; conflicts: number; errors: number }>('sync_push', {
                    apiUrl   : window.location.origin,
                    apiToken : '',
                    deviceId : getDeviceId(),
                })
                offlineStore.setSyncSuccess(result.synced, result.conflicts)
            } else {
                // Navigateur : envoi direct via fetch
                await syncBrowserQueue()
            }
        } catch (err: any) {
            offlineStore.setSyncError(err?.message ?? 'Erreur inconnue')
        }
    }

    /**
     * Sync navigateur : lit IndexedDB et envoie chaque opération au serveur.
     */
    async function syncBrowserQueue(): Promise<void> {
        const pending = await idbGetPending()
        if (!pending.length) {
            offlineStore.setSyncSuccess(0, 0)
            return
        }

        let synced = 0
        let errors = 0

        for (const op of pending) {
            try {
                const payload = JSON.parse(op.payload)
                const url     = resolveUrl(op.table_name, op.record_id, op.operation)
                const method  = resolveMethod(op.operation)

                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type' : 'application/json',
                        'X-CSRF-TOKEN' : getCsrfToken(),
                        'X-Inertia'    : 'true',
                        'Accept'       : 'application/json',
                    },
                    body: JSON.stringify(payload),
                })

                if (res.ok) {
                    await idbMarkSynced(op.id)
                    synced++
                } else {
                    errors++
                }
            } catch {
                errors++
            }
        }

        offlineStore.setSyncSuccess(synced, 0)
        if (errors > 0) {
            offlineStore.setSyncError(`${errors} opération(s) en erreur`)
        }
    }

    return { queueOperation, syncPending }
}

// ── Utilitaires ────────────────────────────────────────────────────────────

function resolveUrl(tableName: string, recordId: string, operation: string): string {
    const map: Record<string, string> = {
        clients      : '/clients',
        fournisseurs : '/fournisseurs',
        produits     : '/produits',
    }
    const base = map[tableName] ?? `/${tableName}`
    if (operation === 'create') return base
    return `${base}/${recordId}`
}

function resolveMethod(operation: string): string {
    if (operation === 'create') return 'POST'
    if (operation === 'update') return 'PUT'
    if (operation === 'delete') return 'DELETE'
    return 'POST'
}

function getCsrfToken(): string {
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
