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
