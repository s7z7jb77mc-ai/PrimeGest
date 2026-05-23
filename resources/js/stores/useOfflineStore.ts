import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useOfflineStore = defineStore('offline', () => {

    // ── État ─────────────────────────────────────────────────────────────────

    const isOnline        = ref<boolean>(navigator.onLine)
    const pendingCount    = ref<number>(0)
    const lastSyncAt      = ref<number>(0)       // UNIX timestamp
    const isSyncing       = ref<boolean>(false)
    const lastSyncError   = ref<string | null>(null)
    const conflicts       = ref<number>(0)
    const isTauri         = ref<boolean>(false)
    const syncToken       = ref<string | null>(null)

    // ── Getters ──────────────────────────────────────────────────────────────

    const hasPending = computed(() => pendingCount.value > 0)

    const syncStatusLabel = computed(() => {
        if (!isOnline.value)      return 'Hors ligne'
        if (isSyncing.value)      return 'Synchronisation...'
        if (lastSyncError.value)  return 'Erreur de sync'
        if (hasPending.value)     return `${pendingCount.value} en attente`
        if (lastSyncAt.value > 0) return 'Synchronisé'
        return 'Prêt'
    })

    const syncStatusColor = computed(() => {
        if (!isOnline.value)      return 'gray'
        if (isSyncing.value)      return 'blue'
        if (lastSyncError.value)  return 'red'
        if (hasPending.value)     return 'amber'
        return 'green'
    })

    const lastSyncLabel = computed(() => {
        if (!lastSyncAt.value) return 'Jamais synchronisé'
        const diff = Math.floor((Date.now() / 1000) - lastSyncAt.value)
        if (diff < 60)   return 'À l\'instant'
        if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`
        return `Il y a ${Math.floor(diff / 3600)} h`
    })

    // ── Actions ──────────────────────────────────────────────────────────────

    function setOnline(value: boolean) {
        isOnline.value = value
    }

    function setPendingCount(count: number) {
        pendingCount.value = count
    }

    function setSyncing(value: boolean) {
        isSyncing.value = value
    }

    function setSyncSuccess(synced: number, conflictsCount: number) {
        isSyncing.value   = false
        lastSyncError.value = null
        lastSyncAt.value  = Math.floor(Date.now() / 1000)
        conflicts.value   = conflictsCount
        // Décrémenter le pending
        pendingCount.value = Math.max(0, pendingCount.value - synced)
    }

    function setSyncError(error: string) {
        isSyncing.value  = false
        lastSyncError.value = error
    }

    function incrementPending() {
        pendingCount.value++
    }

    function setTauri(value: boolean) {
        isTauri.value = value
    }

    function setSyncToken(token: string) {
        syncToken.value = token
    }

    function clearSyncToken() {
        syncToken.value = null
    }

    // Initialiser les listeners réseau
    function initNetworkListeners() {
        const handleOnline = async () => {
            isOnline.value = true
            window.dispatchEvent(new CustomEvent('primegest:online'))
            // Attendre 2s que la session Laravel se rafraîchisse
            await new Promise(resolve => setTimeout(resolve, 2000))
            // Sync automatique au retour online
            try {
                const { useOfflineQueue } = await import('@/composables/useOfflineQueue')
                const { syncPending } = useOfflineQueue()
                await syncPending()
            } catch (e) {
                console.warn('[Offline] Sync échouée:', e)
            }
        }
        const handleOffline = () => {
            isOnline.value = false
            window.dispatchEvent(new CustomEvent('primegest:offline'))
        }
        window.addEventListener('online',  handleOnline)
        window.addEventListener('offline', handleOffline)
        // État initial
        isOnline.value = navigator.onLine
    }

    return {
        // état
        isOnline,
        pendingCount,
        lastSyncAt,
        isSyncing,
        lastSyncError,
        conflicts,
        isTauri,
        syncToken,
        // getters
        hasPending,
        syncStatusLabel,
        syncStatusColor,
        lastSyncLabel,
        // actions
        setOnline,
        setPendingCount,
        setSyncing,
        setSyncSuccess,
        setSyncError,
        incrementPending,
        setTauri,
        setSyncToken,
        clearSyncToken,
        initNetworkListeners,
    }
})
