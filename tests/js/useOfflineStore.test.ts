import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// ── setup ─────────────────────────────────────────────────────────────────

beforeEach(() => {
    setActivePinia(createPinia())
})

// ─────────────────────────────────────────────────────────────────────────
// État initial
// ─────────────────────────────────────────────────────────────────────────

describe('état initial', () => {
    it('pendingCount est 0', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.pendingCount).toBe(0)
    })

    it('isSyncing est false', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.isSyncing).toBe(false)
    })

    it('lastSyncError est null', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.lastSyncError).toBeNull()
    })

    it('isTauri est false par défaut', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.isTauri).toBe(false)
    })

    it('syncToken est null par défaut', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.syncToken).toBeNull()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// setOnline
// ─────────────────────────────────────────────────────────────────────────

describe('setOnline', () => {
    it('setOnline(false) passe isOnline à false', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(false)
        expect(store.isOnline).toBe(false)
    })

    it('setOnline(true) passe isOnline à true', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(false)
        store.setOnline(true)
        expect(store.isOnline).toBe(true)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// incrementPending / setPendingCount
// ─────────────────────────────────────────────────────────────────────────

describe('incrementPending / setPendingCount', () => {
    it('incrementPending augmente le compteur de 1', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.incrementPending()
        store.incrementPending()
        expect(store.pendingCount).toBe(2)
    })

    it('setPendingCount fixe le compteur à la valeur donnée', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.incrementPending()
        store.setPendingCount(7)
        expect(store.pendingCount).toBe(7)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// setSyncing
// ─────────────────────────────────────────────────────────────────────────

describe('setSyncing', () => {
    it('setSyncing(true) active le flag', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncing(true)
        expect(store.isSyncing).toBe(true)
    })

    it('setSyncing(false) désactive le flag', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncing(true)
        store.setSyncing(false)
        expect(store.isSyncing).toBe(false)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// setSyncSuccess
// ─────────────────────────────────────────────────────────────────────────

describe('setSyncSuccess', () => {
    it('met isSyncing à false', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncing(true)
        store.setSyncSuccess(1, 0)
        expect(store.isSyncing).toBe(false)
    })

    it('efface lastSyncError', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncError('Erreur précédente')
        store.setSyncSuccess(0, 0)
        expect(store.lastSyncError).toBeNull()
    })

    it('met lastSyncAt à un timestamp récent', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        const before = Math.floor(Date.now() / 1000) - 1
        store.setSyncSuccess(0, 0)
        expect(store.lastSyncAt).toBeGreaterThan(before)
    })

    it('décrémente pendingCount par synced', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setPendingCount(5)
        store.setSyncSuccess(3, 0)
        expect(store.pendingCount).toBe(2)
    })

    it('ne descend pas sous zéro', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setPendingCount(1)
        store.setSyncSuccess(10, 0) // plus que le pending
        expect(store.pendingCount).toBe(0)
    })

    it('enregistre le nombre de conflits', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncSuccess(2, 3)
        expect(store.conflicts).toBe(3)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// setSyncError
// ─────────────────────────────────────────────────────────────────────────

describe('setSyncError', () => {
    it('stocke le message d\'erreur', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncError('Connexion refusée')
        expect(store.lastSyncError).toBe('Connexion refusée')
    })

    it('met isSyncing à false', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncing(true)
        store.setSyncError('Timeout')
        expect(store.isSyncing).toBe(false)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// setTauri / setSyncToken / clearSyncToken
// ─────────────────────────────────────────────────────────────────────────

describe('setTauri / setSyncToken / clearSyncToken', () => {
    it('setTauri(true) active le flag isTauri', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setTauri(true)
        expect(store.isTauri).toBe(true)
    })

    it('setSyncToken stocke le token', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncToken('sanctum-abc-123')
        expect(store.syncToken).toBe('sanctum-abc-123')
    })

    it('clearSyncToken efface le token', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncToken('token')
        store.clearSyncToken()
        expect(store.syncToken).toBeNull()
    })
})

// ─────────────────────────────────────────────────────────────────────────
// computed — hasPending
// ─────────────────────────────────────────────────────────────────────────

describe('computed hasPending', () => {
    it('est false quand pendingCount = 0', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.hasPending).toBe(false)
    })

    it('est true quand pendingCount > 0', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.incrementPending()
        expect(store.hasPending).toBe(true)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// computed — syncStatusLabel
// ─────────────────────────────────────────────────────────────────────────

describe('computed syncStatusLabel', () => {
    it('retourne "Hors ligne" si offline', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(false)
        expect(store.syncStatusLabel).toBe('Hors ligne')
    })

    it('retourne "Synchronisation..." si isSyncing', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setSyncing(true)
        expect(store.syncStatusLabel).toBe('Synchronisation...')
    })

    it('retourne "Erreur de sync" si lastSyncError', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setSyncError('Timeout')
        expect(store.syncStatusLabel).toBe('Erreur de sync')
    })

    it('retourne "N en attente" si pending > 0', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setPendingCount(4)
        expect(store.syncStatusLabel).toBe('4 en attente')
    })

    it('retourne "Synchronisé" si lastSyncAt > 0', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setSyncSuccess(0, 0)
        expect(store.syncStatusLabel).toBe('Synchronisé')
    })

    it('retourne "Prêt" dans l\'état initial online sans sync', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        expect(store.syncStatusLabel).toBe('Prêt')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// computed — syncStatusColor
// ─────────────────────────────────────────────────────────────────────────

describe('computed syncStatusColor', () => {
    it('gray si offline', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(false)
        expect(store.syncStatusColor).toBe('gray')
    })

    it('blue si syncing', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setSyncing(true)
        expect(store.syncStatusColor).toBe('blue')
    })

    it('red si erreur', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setSyncError('Erreur')
        expect(store.syncStatusColor).toBe('red')
    })

    it('amber si pending', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.setPendingCount(2)
        expect(store.syncStatusColor).toBe('amber')
    })

    it('green si tout OK', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        expect(store.syncStatusColor).toBe('green')
    })
})

// ─────────────────────────────────────────────────────────────────────────
// computed — lastSyncLabel
// ─────────────────────────────────────────────────────────────────────────

describe('computed lastSyncLabel', () => {
    it('retourne "Jamais synchronisé" si lastSyncAt = 0', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        expect(store.lastSyncLabel).toBe('Jamais synchronisé')
    })

    it('retourne "À l\'instant" si sync il y a moins de 60s', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setSyncSuccess(0, 0) // met lastSyncAt = maintenant
        expect(store.lastSyncLabel).toBe('À l\'instant')
    })

    it('retourne "Il y a N min" si sync entre 60s et 1h', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        // Forcer lastSyncAt à il y a 5 min
        store['lastSyncAt'] = Math.floor(Date.now() / 1000) - 300
        expect(store.lastSyncLabel).toMatch(/Il y a 5 min/)
    })

    it('retourne "Il y a N h" si sync il y a plus d\'1h', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store['lastSyncAt'] = Math.floor(Date.now() / 1000) - 7200
        expect(store.lastSyncLabel).toMatch(/Il y a 2 h/)
    })
})

// ─────────────────────────────────────────────────────────────────────────
// initNetworkListeners
// ─────────────────────────────────────────────────────────────────────────

describe('initNetworkListeners', () => {
    it('passe isOnline à false sur événement offline', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.setOnline(true)
        store.initNetworkListeners()

        window.dispatchEvent(new Event('offline'))
        expect(store.isOnline).toBe(false)
    })

    it('dispatche primegest:online sur événement online', async () => {
        const { useOfflineStore } = await import('@/stores/useOfflineStore')
        const store = useOfflineStore()
        store.initNetworkListeners()

        const events: Event[] = []
        window.addEventListener('primegest:online', (e) => events.push(e))

        window.dispatchEvent(new Event('online'))
        // Attendre le tick async de handleOnline
        await new Promise(resolve => setTimeout(resolve, 10))

        expect(events.length).toBeGreaterThanOrEqual(1)
    })
})
