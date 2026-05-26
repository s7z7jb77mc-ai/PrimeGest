import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { db } from '@/db/primegest'
import { getDeviceId, useOfflineQueue } from '@/composables/useOfflineQueue'

// ── helpers ───────────────────────────────────────────────────────────────

function makeJsonResponse(body: object, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    }) as Response
}

async function seedPendingOp(tableName = 'clients', recordId = crypto.randomUUID()) {
    await db.sync_queue.add({
        table_name:          tableName,
        record_id:           recordId,
        operation:           'create',
        payload:             JSON.stringify({ nom_client: 'Test' }),
        status:              'pending',
        client_sync_version: 0,
        created_at:          Date.now(),
    })
    return recordId
}

// ── setup / teardown ──────────────────────────────────────────────────────

beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    await db.sync_queue.clear()
    await db.clients.clear()
})

afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
})

// ─────────────────────────────────────────────────────────────────────────
// getDeviceId
// ─────────────────────────────────────────────────────────────────────────

describe('getDeviceId', () => {
    it('génère un UUID valide et le persiste', () => {
        const id = getDeviceId()
        expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/)
        expect(localStorage.getItem('primegest_device_id')).toBe(id)
    })

    it('retourne le même ID à chaque appel', () => {
        const id1 = getDeviceId()
        const id2 = getDeviceId()
        expect(id1).toBe(id2)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// queueOperation — chemin navigateur (isTauri = false)
// ─────────────────────────────────────────────────────────────────────────

describe('queueOperation', () => {
    it('ajoute une entrée pending dans sync_queue', async () => {
        const { queueOperation } = useOfflineQueue()
        const uuid = crypto.randomUUID()

        await queueOperation('clients', uuid, 'create', { nom_client: 'Alice' })

        const items = await db.sync_queue.toArray()
        expect(items).toHaveLength(1)
        expect(items[0]).toMatchObject({
            table_name: 'clients',
            record_id:  uuid,
            operation:  'create',
            status:     'pending',
        })
    })

    it('sérialise le payload en JSON', async () => {
        const { queueOperation } = useOfflineQueue()
        const uuid    = crypto.randomUUID()
        const payload = { nom_client: 'Bob', numero_telephone: '0999111111' }

        await queueOperation('clients', uuid, 'create', payload)

        const items = await db.sync_queue.toArray()
        expect(JSON.parse(items[0].payload as string)).toEqual(payload)
    })

    it('incrémente le pendingCount du store', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        const { queueOperation } = useOfflineQueue()

        expect(store.pendingCount).toBe(0)
        await queueOperation('produits', crypto.randomUUID(), 'update', { nom: 'Prod' })
        expect(store.pendingCount).toBe(1)
    })

    it('le write-through dispatche primegest:local-write', async () => {
        const { queueOperation } = useOfflineQueue()
        const dispatched: string[] = []
        window.addEventListener('primegest:local-write', (e) => {
            dispatched.push((e as CustomEvent).detail.tableName)
        })

        await queueOperation('clients', crypto.randomUUID(), 'create', { nom_client: 'Eve' })

        expect(dispatched).toContain('clients')
    })

    it('le write-through upserte le record dans db.clients', async () => {
        const { queueOperation } = useOfflineQueue()
        const uuid = crypto.randomUUID()

        await queueOperation('clients', uuid, 'create', { nom_client: 'Charlie' })

        const stored = await db.clients.get(uuid)
        expect(stored?.nom_client).toBe('Charlie')
        expect(stored?.uuid).toBe(uuid)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// syncPending → syncBrowserQueue
// ─────────────────────────────────────────────────────────────────────────

describe('syncPending / syncBrowserQueue', () => {
    it('ne fait rien si la queue est vide', async () => {
        const fetchSpy = vi.fn()
        vi.stubGlobal('fetch', fetchSpy)

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(fetchSpy).not.toHaveBeenCalled()
    })

    it('envoie les opérations pending vers /api/sync/push', async () => {
        const recordId = await seedPendingOp()

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            makeJsonResponse({ results: [{ record_id: recordId, status: 'synced' }] })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        const [url, opts] = (fetch as any).mock.calls[0]
        expect(url).toBe('/api/sync/push')
        expect(opts.method).toBe('POST')

        const body = JSON.parse(opts.body)
        expect(body.operations[0].record_id).toBe(recordId)
    })

    it('marque les opérations comme synced après succès', async () => {
        const recordId = await seedPendingOp()

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            makeJsonResponse({ results: [{ record_id: recordId, status: 'synced' }] })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        const items = await db.sync_queue.toArray()
        expect(items[0].status).toBe('synced')
    })

    it('met à jour le store après sync réussie', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        const recordId = await seedPendingOp()
        store.incrementPending() // simuler 1 op en attente

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            makeJsonResponse({ results: [{ record_id: recordId, status: 'synced' }] })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(store.isSyncing).toBe(false)
        expect(store.lastSyncError).toBeNull()
        expect(store.lastSyncAt).toBeGreaterThan(0)
    })

    it('gère le statut conflict comme synced (LWW)', async () => {
        const recordId = await seedPendingOp()

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            makeJsonResponse({ results: [{ record_id: recordId, status: 'conflict' }] })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        const item = await db.sync_queue.toArray()
        expect(item[0].status).toBe('synced')
    })

    it('set une erreur store sur 401', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        await seedPendingOp()

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            new Response('Unauthorized', { status: 401 })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(store.lastSyncError).toMatch(/session|reconnect/i)
    })

    it('set une erreur store sur erreur réseau', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        await seedPendingOp()

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(store.lastSyncError).toBeTruthy()
        expect(store.isSyncing).toBe(false)
    })
})
