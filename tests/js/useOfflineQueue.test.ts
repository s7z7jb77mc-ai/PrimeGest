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

    it('set Erreur serveur 500 sur réponse !ok non-401', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        await seedPendingOp()

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            new Response('Internal Server Error', { status: 500 })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(store.lastSyncError).toMatch(/500/)
        expect(store.isSyncing).toBe(false)
    })

    it('reload sur réponse opaqueredirect (302)', async () => {
        await seedPendingOp()

        const reloadSpy = vi.fn()
        vi.stubGlobal('location', { ...window.location, reload: reloadSpy })

        // Response.type est read-only — on retourne un objet plain qui imite une opaqueredirect
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ type: 'opaqueredirect', status: 0, ok: false }))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(reloadSpy).toHaveBeenCalled()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// writeThrough — opération delete
// ─────────────────────────────────────────────────────────────────────────

describe('writeThrough delete', () => {
    it('marque deleted_at sur le record local sans le supprimer', async () => {
        const uuid = crypto.randomUUID()

        // Pré-seed le record dans Dexie
        await db.clients.put({ uuid, nom_client: 'À supprimer', updated_at: 0, deleted_at: null })

        const { queueOperation } = useOfflineQueue()
        await queueOperation('clients', uuid, 'delete', {})

        const stored = await db.clients.get(uuid)
        expect(stored).toBeDefined()
        expect(stored?.deleted_at).toBeGreaterThan(0)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// writeThrough — opération update
// ─────────────────────────────────────────────────────────────────────────

describe('writeThrough update', () => {
    it('met à jour un record existant dans db.clients', async () => {
        const uuid = crypto.randomUUID()

        await db.clients.put({ uuid, nom_client: 'Ancien nom', updated_at: 0, deleted_at: null })

        const { queueOperation } = useOfflineQueue()
        await queueOperation('clients', uuid, 'update', { nom_client: 'Nouveau nom' })

        const stored = await db.clients.get(uuid)
        expect(stored?.nom_client).toBe('Nouveau nom')
        expect(stored?.deleted_at).toBeNull()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// writeThrough — table produits (vérifier que la map couvre d'autres tables)
// ─────────────────────────────────────────────────────────────────────────

describe('writeThrough produits', () => {
    beforeEach(async () => {
        await db.produits.clear()
    })

    it('upserte un produit dans db.produits', async () => {
        const uuid = crypto.randomUUID()
        const { queueOperation } = useOfflineQueue()

        await queueOperation('produits', uuid, 'create', {
            nom: 'Aspirine 500mg',
            prix_vente: 1000,
            prix_achat: 500,
        })

        const stored = await db.produits.get(uuid)
        expect(stored?.uuid).toBe(uuid)
        expect(stored?.nom).toBe('Aspirine 500mg')
    })

    it('dispatche primegest:local-write avec table produits', async () => {
        const dispatched: string[] = []
        window.addEventListener('primegest:local-write', (e) => {
            dispatched.push((e as CustomEvent).detail.tableName)
        })

        const { queueOperation } = useOfflineQueue()
        await queueOperation('produits', crypto.randomUUID(), 'create', { nom: 'Test', prix_vente: 500, prix_achat: 200 })

        expect(dispatched).toContain('produits')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// syncPending — verrou isSyncing
// ─────────────────────────────────────────────────────────────────────────

describe('syncPending isSyncing lock', () => {
    it('ne lance pas un deuxième fetch si déjà en cours', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()

        store.setSyncing(true)

        const fetchSpy = vi.fn()
        vi.stubGlobal('fetch', fetchSpy)

        await db.sync_queue.add({
            table_name: 'clients', record_id: crypto.randomUUID(),
            operation: 'create', payload: JSON.stringify({ nom_client: 'Test' }),
            status: 'pending', client_sync_version: 0, created_at: Date.now(),
        })

        const { syncPending } = useOfflineQueue()
        await syncPending()

        expect(fetchSpy).not.toHaveBeenCalled()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// syncPending — batch multi-tables
// ─────────────────────────────────────────────────────────────────────────

describe('syncPending batch multi-tables', () => {
    beforeEach(async () => {
        await db.produits.clear()
    })

    it('inclut les ops de tables différentes dans le même batch', async () => {
        const clientId  = crypto.randomUUID()
        const produitId = crypto.randomUUID()

        await db.sync_queue.add({
            table_name: 'clients', record_id: clientId, operation: 'create',
            payload: JSON.stringify({ nom_client: 'Batch A', numero_telephone: '0999111111' }),
            status: 'pending', client_sync_version: 0, created_at: Date.now(),
        })
        await db.sync_queue.add({
            table_name: 'produits', record_id: produitId, operation: 'create',
            payload: JSON.stringify({ nom: 'Batch B', prix_vente: 500, prix_achat: 200 }),
            status: 'pending', client_sync_version: 0, created_at: Date.now(),
        })

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            new Response(JSON.stringify({
                results: [
                    { record_id: clientId,  status: 'synced' },
                    { record_id: produitId, status: 'synced' },
                ],
            }), { status: 200, headers: { 'Content-Type': 'application/json' } })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        const [, opts] = (fetch as any).mock.calls[0]
        const body     = JSON.parse(opts.body)

        const tableNames = body.operations.map((o: any) => o.table_name)
        expect(tableNames).toContain('clients')
        expect(tableNames).toContain('produits')
        expect(body.operations).toHaveLength(2)
    })

    it('marque les deux ops synced après un batch réussi', async () => {
        const id1 = crypto.randomUUID()
        const id2 = crypto.randomUUID()

        await db.sync_queue.add({
            table_name: 'clients', record_id: id1, operation: 'create',
            payload: JSON.stringify({ nom_client: 'A', numero_telephone: '0999222222' }),
            status: 'pending', client_sync_version: 0, created_at: Date.now(),
        })
        await db.sync_queue.add({
            table_name: 'produits', record_id: id2, operation: 'create',
            payload: JSON.stringify({ nom: 'B', prix_vente: 100 }),
            status: 'pending', client_sync_version: 0, created_at: Date.now(),
        })

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            new Response(JSON.stringify({
                results: [
                    { record_id: id1, status: 'synced' },
                    { record_id: id2, status: 'synced' },
                ],
            }), { status: 200, headers: { 'Content-Type': 'application/json' } })
        ))

        const { syncPending } = useOfflineQueue()
        await syncPending()

        const items = await db.sync_queue.toArray()
        expect(items.every(i => i.status === 'synced')).toBe(true)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// writeThrough — table inconnue est ignorée silencieusement
// ─────────────────────────────────────────────────────────────────────────

describe('writeThrough table inconnue', () => {
    it('ignore silencieusement les tables non mappées', async () => {
        const { queueOperation } = useOfflineQueue()

        // Ne doit pas throw même si la table n'existe pas dans Dexie
        await expect(
            queueOperation('users', crypto.randomUUID(), 'create', { email: 'hack@test.com' })
        ).resolves.toBeUndefined()
    })
})
