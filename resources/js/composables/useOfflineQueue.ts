import { db } from '@/db/primegest'
import { useOfflineStore } from '@/stores/useOfflineStore'

const isTauri = typeof window !== 'undefined' && '__TAURI_INTERNALS__' in window

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
        }
        offlineStore.incrementPending()
    }

    async function syncPending(): Promise<void> {
        if (offlineStore.isSyncing) return
        offlineStore.setSyncing(true)

        try {
            if (isTauri) {
                const { invoke } = await import('@tauri-apps/api/core')
                const result = await invoke<{ synced: number; conflicts: number; errors: number }>('sync_push', {
                    apiUrl:   window.location.origin,
                    apiToken: localStorage.getItem('api_token') ?? '',
                    deviceId: getDeviceId(),
                })
                await invoke<number>('sync_pull', {
                    apiUrl:     window.location.origin,
                    apiToken:   localStorage.getItem('api_token') ?? '',
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
                conflicts++
            }
        }

        offlineStore.setSyncSuccess(synced, conflicts)
    }

    return { queueOperation, syncPending }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    if (match) return decodeURIComponent(match[1])
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
