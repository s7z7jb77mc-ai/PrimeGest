import axios from 'axios';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useSyncStore = defineStore('sync', () => {
    const pendingCount = ref(0);
    const isOnline = ref(navigator.onLine);
    const isSyncing = ref(false);
    const lastSyncAt = ref<string | null>(null);

    const status = computed(() => {
        if (!isOnline.value) return 'offline';
        if (isSyncing.value) return 'syncing';
        if (pendingCount.value > 0) return 'pending';
        return 'synced';
    });

    const statusLabel = computed(
        () =>
            ({
                offline: '🔴 Hors ligne',
                syncing: '🔄 Synchronisation...',
                pending: `🟡 En attente (${pendingCount.value})`,
                synced: '🟢 Synchronisé',
            })[status.value],
    );

    async function refresh() {
        try {
            const { data } = await axios.get('/api/local/sync-status');
            pendingCount.value = data.pending_count;
            isSyncing.value = data.is_syncing;
            lastSyncAt.value = data.last_sync_at;
        } catch {
            isOnline.value = false;
        }
    }

    window.addEventListener('online', () => {
        isOnline.value = true;
        refresh();
    });
    window.addEventListener('offline', () => {
        isOnline.value = false;
    });

    setInterval(refresh, 30_000);
    refresh();

    return { status, statusLabel, pendingCount, isOnline, isSyncing, lastSyncAt, refresh };
});
