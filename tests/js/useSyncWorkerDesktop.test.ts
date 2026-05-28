/**
 * Tests du chemin Tauri/desktop : useSyncWorker.
 *
 * useSyncWorker est le composable utilisé dans la version desktop (Tauri).
 * Il conditionne toutes ses actions sur offlineStore.isTauri (valeur runtime,
 * pas une constante de module), ce qui permet de le tester en positionnant
 * simplement le flag dans le store Pinia.
 *
 * Les hooks Vue (onMounted, onUnmounted) et usePage (Inertia) sont mockés
 * pour que le composable puisse être appelé hors d'un composant.
 */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// ── Mocks globaux (levés en haut du module par Vitest) ────────────────────

// Permettre l'appel de useSyncWorker hors contexte composant
vi.mock('vue', async (importOriginal) => {
    const original = await importOriginal<typeof import('vue')>()
    return {
        ...original,
        onMounted:   vi.fn(),
        onUnmounted: vi.fn(),
    }
})

// InertiaJS usePage requiert un contexte d'app — on le rend autonome
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: {} }),
}))

// Mock de l'API Tauri — sera configuré dans chaque describe
vi.mock('@tauri-apps/api/core', () => ({
    invoke: vi.fn(),
}))

// ── setup / teardown ──────────────────────────────────────────────────────

beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
})

afterEach(() => {
    vi.restoreAllMocks()
})

// ─────────────────────────────────────────────────────────────────────────
// runSync — guards
// ─────────────────────────────────────────────────────────────────────────

describe('runSync — guards', () => {
    it('ne fait rien si isTauri = false', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(false)
        store.setOnline(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(invoke).not.toHaveBeenCalled()
    })

    it('ne fait rien si offline', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(false)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(invoke).not.toHaveBeenCalled()
    })

    it('ne fait rien si isSyncing = true (verrou)', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)
        store.setSyncing(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(invoke).not.toHaveBeenCalled()
    })

    it('ne fait rien si token absent du localStorage', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)
        // Pas de token → localStorage vide

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(invoke).not.toHaveBeenCalled()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// runSync — chemin heureux (Tauri + online + token)
// ─────────────────────────────────────────────────────────────────────────

describe('runSync — chemin heureux', () => {
    beforeEach(() => {
        localStorage.setItem('api_token', 'sanctum-test-token')
    })

    it('appelle invoke sync_push avec apiUrl, apiToken, deviceId', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke)
            .mockResolvedValueOnce({ synced: 2, conflicts: 0, errors: 0 }) // sync_push
            .mockResolvedValueOnce(3) // sync_pull

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        const pushCall = vi.mocked(invoke).mock.calls.find(c => c[0] === 'sync_push')
        expect(pushCall).toBeDefined()
        expect(pushCall![1]).toMatchObject({
            apiToken: 'sanctum-test-token',
        })
    })

    it('appelle invoke sync_pull après sync_push', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke)
            .mockResolvedValueOnce({ synced: 1, conflicts: 0, errors: 0 })
            .mockResolvedValueOnce(0)

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        const pullCall = vi.mocked(invoke).mock.calls.find(c => c[0] === 'sync_pull')
        expect(pullCall).toBeDefined()
    })

    it('met isSyncing = false après succès', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke)
            .mockResolvedValueOnce({ synced: 0, conflicts: 0, errors: 0 })
            .mockResolvedValueOnce(0)

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(store.isSyncing).toBe(false)
    })

    it('dispatche primegest:sync-pulled si des records ont été tirés', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke)
            .mockResolvedValueOnce({ synced: 1, conflicts: 0, errors: 0 })
            .mockResolvedValueOnce(5) // 5 records pulled

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)

        const events: CustomEvent[] = []
        window.addEventListener('primegest:sync-pulled', e => events.push(e as CustomEvent))

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(events.length).toBeGreaterThanOrEqual(1)
        expect(events[0].detail.count).toBe(5)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// runSync — gestion d'erreur
// ─────────────────────────────────────────────────────────────────────────

describe('runSync — gestion d\'erreur', () => {
    beforeEach(() => {
        localStorage.setItem('api_token', 'sanctum-test-token')
    })

    it('set lastSyncError et remet isSyncing = false si invoke échoue', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke).mockRejectedValue(new Error('Réseau Tauri indisponible'))

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().runSync()

        expect(store.lastSyncError).toBeTruthy()
        expect(store.isSyncing).toBe(false)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// queueOperation — chemin Tauri
// ─────────────────────────────────────────────────────────────────────────

describe('queueOperation — chemin Tauri', () => {
    it('ne fait rien si isTauri = false', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setTauri(false)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().queueOperation('clients', crypto.randomUUID(), 'create', { nom: 'X' })

        expect(invoke).not.toHaveBeenCalled()
    })

    it('appelle invoke queue_operation quand Tauri actif', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke).mockResolvedValue(undefined)

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(false) // offline → pas de runSync déclenché

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        const uuid = crypto.randomUUID()
        await useSyncWorker().queueOperation('clients', uuid, 'create', { nom_client: 'Test Tauri' })

        expect(invoke).toHaveBeenCalledWith('queue_operation', {
            tableName:  'clients',
            recordId:   uuid,
            operation:  'create',
            payload:    { nom_client: 'Test Tauri' },
        })
    })

    it('incrémente pendingCount', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke).mockResolvedValue(undefined)

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        store.setOnline(false)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().queueOperation('produits', crypto.randomUUID(), 'update', { nom: 'Mis à jour' })

        expect(store.pendingCount).toBe(1)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// initTauriToken
// ─────────────────────────────────────────────────────────────────────────

describe('initTauriToken', () => {
    it('ne fait rien si isTauri = false', async () => {
        const fetchSpy = vi.fn()
        vi.stubGlobal('fetch', fetchSpy)

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setTauri(false)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().initTauriToken()

        expect(fetchSpy).not.toHaveBeenCalled()
    })

    it('ne fetch pas si un token est déjà présent dans localStorage', async () => {
        localStorage.setItem('api_token', 'existing-token')
        const fetchSpy = vi.fn()
        vi.stubGlobal('fetch', fetchSpy)

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setTauri(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().initTauriToken()

        expect(fetchSpy).not.toHaveBeenCalled()
    })

    it('fetche le token depuis /api/auth/tauri-token et le stocke', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ token: 'nouveau-token-sanctum' }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        ))

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setTauri(true)
        // Pas de token existant

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().initTauriToken()

        expect(localStorage.getItem('api_token')).toBe('nouveau-token-sanctum')
    })

    it('gère silencieusement une erreur réseau', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('réseau indisponible')))

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setTauri(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        // Ne doit pas lever d'exception
        await expect(useSyncWorker().initTauriToken()).resolves.toBeUndefined()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// refreshStatus
// ─────────────────────────────────────────────────────────────────────────

describe('refreshStatus', () => {
    it('ne fait rien si isTauri = false', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        useOfflineStore().setTauri(false)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().refreshStatus()

        expect(invoke).not.toHaveBeenCalled()
    })

    it('appelle invoke get_sync_status et met à jour pendingCount', async () => {
        const { invoke } = await import('@tauri-apps/api/core')
        vi.mocked(invoke).mockResolvedValue({ pending_count: 7, last_sync_ts: 1700000000, is_online: true })

        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)

        const { useSyncWorker } = await import('@/composables/useSyncWorker')
        await useSyncWorker().refreshStatus()

        expect(store.pendingCount).toBe(7)
    })
})
