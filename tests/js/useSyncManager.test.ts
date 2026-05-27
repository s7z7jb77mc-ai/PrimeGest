import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { db } from '@/db/primegest'

// ── helpers ───────────────────────────────────────────────────────────────

function makeDeltaResponse(extra: object = {}): Response {
    const body = {
        server_ts: Math.floor(Date.now() / 1000),
        delta: {
            clients: [],
            produits: [],
            caisses: [],
            journals: [],
            mouvement_stocks: [],
            fournisseurs: [],
            transferts: [],
            succursales: [],
            stocks: [],
            ...extra,
        },
    }
    return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    })
}

// ── setup / teardown ──────────────────────────────────────────────────────

beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    await db.sync_meta.clear()
    await db.clients.clear()
    await db.produits.clear()
    await db.caisses.clear()
})

afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
})

// ─────────────────────────────────────────────────────────────────────────
// pull
// ─────────────────────────────────────────────────────────────────────────

describe('pull', () => {
    it('ne fait pas de fetch si offline', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(false)

        const fetchSpy = vi.fn()
        vi.stubGlobal('fetch', fetchSpy)

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        expect(fetchSpy).not.toHaveBeenCalled()
    })

    it('appelle /api/sync/pull?since=0 au premier pull', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse()))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const url = (fetch as any).mock.calls[0][0] as string
        expect(url).toContain('/api/sync/pull')
        expect(url).toContain('since=0')
    })

    it('utilise last_sync_at de sync_meta si disponible', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        await db.sync_meta.put({ table_name: '_global', last_sync_at: 1700000000 })

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse()))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const url = (fetch as any).mock.calls[0][0] as string
        expect(url).toContain('since=1700000000')
    })

    it('upserte les clients du delta dans db.clients', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const uuid = crypto.randomUUID()
        const now  = Math.floor(Date.now() / 1000)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            clients: [{
                uuid,
                updated_at_ts: now,
                deleted_at:    null,
                sync_version:  1,
                payload:       { nom_client: 'Delta Client', numero_telephone: '0999000007' },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const stored = await db.clients.get(uuid)
        expect(stored?.nom_client).toBe('Delta Client')
        expect(stored?.uuid).toBe(uuid)
        expect(stored?.updated_at).toBe(now)
        expect(stored?.deleted_at).toBeNull()
    })

    it('marque les records supprimés (deleted_at non null)', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const uuid      = crypto.randomUUID()
        const now       = Math.floor(Date.now() / 1000)
        const deletedAt = now - 60

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            clients: [{
                uuid,
                updated_at_ts: now,
                deleted_at:    deletedAt,
                sync_version:  2,
                payload:       { nom_client: 'Supprimé' },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const stored = await db.clients.get(uuid)
        expect(stored?.deleted_at).toBe(deletedAt)
    })

    it('met à jour sync_meta._global.last_sync_at après pull', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const before = Math.floor(Date.now() / 1000) - 1
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse()))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const meta = await db.sync_meta.get('_global')
        expect(meta?.last_sync_at).toBeGreaterThan(before)
    })

    it('dispatche primegest:sync-pulled quand des records arrivent', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const events: CustomEvent[] = []
        window.addEventListener('primegest:sync-pulled', (e) => events.push(e as CustomEvent))

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            produits: [{
                uuid:          crypto.randomUUID(),
                updated_at_ts: Math.floor(Date.now() / 1000),
                deleted_at:    null,
                sync_version:  1,
                payload:       { nom: 'Prod A', prix_vente: 1000, prix_achat: 800 },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        expect(events).toHaveLength(1)
        expect(events[0].detail.count).toBe(1)
    })

    it('ne dispatche pas primegest:sync-pulled si delta vide', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const events: CustomEvent[] = []
        window.addEventListener('primegest:sync-pulled', (e) => events.push(e as CustomEvent))

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse()))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        expect(events).toHaveLength(0)
    })

    it('ignore silencieusement une erreur réseau', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        // ne doit pas lever d'exception
        await expect(useSyncManager().pull()).resolves.toBeUndefined()
    })

    it('ne redirige pas en web sur 401 (évite la boucle)', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 401 })))

        // Capturer l'URL avant l'appel — en web (pas Tauri), elle ne doit pas changer
        const hrefBefore = window.location.href

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        expect(window.location.href).toBe(hrefBefore)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// pull — multi-table et LWW
// ─────────────────────────────────────────────────────────────────────────

describe('pull — multi-table et LWW', () => {
    beforeEach(async () => {
        await db.fournisseurs.clear()
    })

    it('upserte produits et fournisseurs en même temps', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        await db.fournisseurs.clear()

        const produitUuid    = crypto.randomUUID()
        const fournisseurUuid = crypto.randomUUID()
        const now             = Math.floor(Date.now() / 1000)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            produits: [{
                uuid: produitUuid, updated_at_ts: now, deleted_at: null, sync_version: 1,
                payload: { nom: 'Multi-table produit', prix_vente: 2000, prix_achat: 1000 },
            }],
            fournisseurs: [{
                uuid: fournisseurUuid, updated_at_ts: now, deleted_at: null, sync_version: 1,
                payload: { nom_entreprise_fournisseur: 'Fournisseur Delta', reduction_pourcentage: 5 },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const produit     = await db.produits.get(produitUuid)
        const fournisseur = await db.fournisseurs.get(fournisseurUuid)

        expect(produit?.nom).toBe('Multi-table produit')
        expect(fournisseur?.nom_entreprise_fournisseur).toBe('Fournisseur Delta')
    })

    it('pull écrase la valeur locale si updated_at_ts est plus récent (LWW pull)', async () => {
        const uuid   = crypto.randomUUID()
        const older  = 1000000
        const newer  = 2000000

        // Seed localement avec une valeur "ancienne"
        await db.clients.put({ uuid, nom_client: 'Ancienne valeur', updated_at: older, deleted_at: null })

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            clients: [{
                uuid, updated_at_ts: newer, deleted_at: null, sync_version: 2,
                payload: { nom_client: 'Valeur serveur plus récente' },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const stored = await db.clients.get(uuid)
        expect(stored?.nom_client).toBe('Valeur serveur plus récente')
        expect(stored?.updated_at).toBe(newer)
    })

    it('pull écrase aussi la valeur locale si updated_at_ts est plus ancien (comportement actuel sans guard LWW)', async () => {
        // Documente le comportement ACTUEL : le pull fait un put() sans vérifier updated_at local.
        // Si le serveur envoie une valeur obsolète, le pull local sera quand même écrasé.
        // TODO: ajouter une garde updated_at > local avant le put() pour corriger ce comportement.
        const uuid   = crypto.randomUUID()
        const newer  = 2000000
        const older  = 1000000

        // Seed localement avec une valeur plus récente
        await db.clients.put({ uuid, nom_client: 'Valeur locale récente', updated_at: newer, deleted_at: null })

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            clients: [{
                uuid, updated_at_ts: older, deleted_at: null, sync_version: 1,
                payload: { nom_client: 'Valeur serveur obsolète' },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        // Comportement actuel : le pull écrase sans LWW guard
        const stored = await db.clients.get(uuid)
        expect(stored?.nom_client).toBe('Valeur serveur obsolète')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// normalizePull — testé via le comportement de pull()
// ─────────────────────────────────────────────────────────────────────────

describe('normalizePull (via pull)', () => {
    it('extrait le payload et updated_at_ts dans la forme stockée', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const uuid = crypto.randomUUID()
        const ts   = 1700000042

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            clients: [{
                uuid,
                updated_at_ts: ts,
                deleted_at:    null,
                sync_version:  3,
                payload:       { nom_client: 'Normalisé', adresse: 'Kinshasa' },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const stored = await db.clients.get(uuid)
        // Les champs payload sont dépliés directement sur l'objet stocké
        expect(stored?.nom_client).toBe('Normalisé')
        expect(stored?.adresse).toBe('Kinshasa')
        // sync_version ne doit PAS être stocké (non inclus dans normalizePull)
        expect((stored as any)?.sync_version).toBeUndefined()
        // updated_at provient de updated_at_ts
        expect(stored?.updated_at).toBe(ts)
    })

    it('gère updated_at en string ISO quand updated_at_ts est absent', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const uuid      = crypto.randomUUID()
        const isoDate   = '2024-11-15T10:30:00Z'
        const expected  = Math.floor(new Date(isoDate).getTime() / 1000)

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(makeDeltaResponse({
            clients: [{
                uuid,
                updated_at:    isoDate,
                deleted_at:    null,
                sync_version:  1,
                payload:       { nom_client: 'IsoDate' },
            }],
        })))

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().pull()

        const stored = await db.clients.get(uuid)
        expect(stored?.updated_at).toBe(expected)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// sync() — push + pull combiné
// ─────────────────────────────────────────────────────────────────────────

describe('sync', () => {
    it('appelle syncPending puis pull quand online', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const fetchSpy = vi.fn()
            .mockResolvedValueOnce(
                // réponse push (syncPending — queue vide, fetch non appelé pour push vide)
                new Response(JSON.stringify({ results: [] }), {
                    status: 200, headers: { 'Content-Type': 'application/json' },
                })
            )
            .mockResolvedValueOnce(makeDeltaResponse())

        vi.stubGlobal('fetch', fetchSpy)

        // seed 1 op pending pour forcer l'appel fetch dans syncPending
        const { db: dexie } = await import('@/db/primegest')
        await dexie.sync_queue.add({
            table_name:          'clients',
            record_id:           crypto.randomUUID(),
            operation:           'create',
            payload:             JSON.stringify({ nom_client: 'Sync Test' }),
            status:              'pending',
            client_sync_version: 0,
            created_at:          Date.now(),
        })

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await useSyncManager().sync()

        // Deux appels fetch : 1 pour push, 1 pour pull
        expect(fetchSpy).toHaveBeenCalledTimes(2)
        expect((fetchSpy.mock.calls[0][0] as string)).toContain('/api/sync/push')
        expect((fetchSpy.mock.calls[1][0] as string)).toContain('/api/sync/pull')
    })

    it('ne crash pas si syncPending échoue', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        // push échoue, pull réussit
        const fetchSpy = vi.fn()
            .mockRejectedValueOnce(new Error('push failed'))
            .mockResolvedValueOnce(makeDeltaResponse())

        vi.stubGlobal('fetch', fetchSpy)

        const { db: dexie } = await import('@/db/primegest')
        await dexie.sync_queue.add({
            table_name: 'produits', record_id: crypto.randomUUID(),
            operation: 'update', payload: '{}', status: 'pending',
            client_sync_version: 0, created_at: Date.now(),
        })

        const { useSyncManager } = await import('@/composables/useSyncManager')
        await expect(useSyncManager().sync()).resolves.toBeUndefined()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// startAutoSync()
// ─────────────────────────────────────────────────────────────────────────

describe('startAutoSync', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })
    afterEach(() => {
        vi.useRealTimers()
    })

    it('déclenche un pull initial immédiatement', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        // mockImplementation pour créer une nouvelle Response à chaque appel (body non réutilisable)
        const fetchSpy = vi.fn().mockImplementation(() => Promise.resolve(makeDeltaResponse()))
        vi.stubGlobal('fetch', fetchSpy)

        const { useSyncManager } = await import('@/composables/useSyncManager')
        useSyncManager().startAutoSync()

        // Avancer de 100ms pour laisser le pull initial se résoudre sans déclencher l'intervalle
        await vi.advanceTimersByTimeAsync(100)

        expect(fetchSpy).toHaveBeenCalledWith(
            expect.stringContaining('/api/sync/pull'),
            expect.anything(),
        )
    })

    it('re-pull toutes les 5 min si online', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const fetchSpy = vi.fn().mockImplementation(() => Promise.resolve(makeDeltaResponse()))
        vi.stubGlobal('fetch', fetchSpy)

        const { useSyncManager } = await import('@/composables/useSyncManager')
        useSyncManager().startAutoSync()

        await vi.advanceTimersByTimeAsync(100) // pull initial

        const countAfterInit = fetchSpy.mock.calls.length

        // Avancer exactement 5 min pour déclencher l'intervalle une fois
        await vi.advanceTimersByTimeAsync(5 * 60 * 1000)

        expect(fetchSpy.mock.calls.length).toBeGreaterThan(countAfterInit)
    })

    it('déclenche sync complet sur événement primegest:online', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setOnline(true)

        const fetchSpy = vi.fn().mockImplementation(() => Promise.resolve(makeDeltaResponse()))
        vi.stubGlobal('fetch', fetchSpy)

        const { useSyncManager } = await import('@/composables/useSyncManager')
        useSyncManager().startAutoSync()
        await vi.advanceTimersByTimeAsync(100) // pull initial

        const countBefore = fetchSpy.mock.calls.length

        // Simuler retour online → déclenche sync() = push + pull
        window.dispatchEvent(new CustomEvent('primegest:online'))
        await vi.advanceTimersByTimeAsync(100)

        expect(fetchSpy.mock.calls.length).toBeGreaterThan(countBefore)
    })
})
