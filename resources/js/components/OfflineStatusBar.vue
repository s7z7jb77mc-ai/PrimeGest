<script setup lang="ts">
import { useSyncWorker } from '../composables/useSyncWorker'
import { useOfflineStore } from '../stores/useOfflineStore'

const offlineStore = useOfflineStore()
const { runSync }  = useSyncWorker()
</script>

<template>
    <div
        v-if="offlineStore.isTauri"
        class="offline-bar"
        :class="`offline-bar--${offlineStore.syncStatusColor}`"
    >
        <!-- Indicateur réseau -->
        <span class="offline-bar__dot" />

        <!-- Label statut -->
        <span class="offline-bar__label">
            {{ offlineStore.syncStatusLabel }}
        </span>

        <!-- Dernière sync -->
        <span class="offline-bar__last">
            {{ offlineStore.lastSyncLabel }}
        </span>

        <!-- Badge conflits -->
        <span
            v-if="offlineStore.conflicts > 0"
            class="offline-bar__badge offline-bar__badge--conflict"
        >
            {{ offlineStore.conflicts }} conflit{{ offlineStore.conflicts > 1 ? 's' : '' }}
        </span>

        <!-- Bouton sync manuel -->
        <button
            class="offline-bar__btn"
            :disabled="offlineStore.isSyncing || !offlineStore.isOnline"
            @click="runSync"
        >
            <svg
                class="offline-bar__icon"
                :class="{ 'offline-bar__icon--spin': offlineStore.isSyncing }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M21 2v6h-6"/>
                <path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
                <path d="M3 22v-6h6"/>
                <path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
            </svg>
        </button>
    </div>
</template>

<style scoped>
.offline-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 12px;
    font-size: 12px;
    border-top: 0.5px solid var(--color-border-tertiary, #e5e7eb);
    background: var(--color-background-secondary, #f9fafb);
    color: var(--color-text-secondary, #6b7280);
    user-select: none;
}

.offline-bar__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.offline-bar--green  .offline-bar__dot { background: #22c55e; }
.offline-bar--amber  .offline-bar__dot { background: #f59e0b; }
.offline-bar--blue   .offline-bar__dot { background: #3b82f6; animation: pulse 1.2s infinite; }
.offline-bar--red    .offline-bar__dot { background: #ef4444; }
.offline-bar--gray   .offline-bar__dot { background: #9ca3af; }

.offline-bar__label { font-weight: 500; }
.offline-bar__last  { margin-left: auto; opacity: 0.7; }

.offline-bar__badge {
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
}
.offline-bar__badge--conflict {
    background: #fef3c7;
    color: #92400e;
}

.offline-bar__btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px;
    color: inherit;
    opacity: 0.7;
    transition: opacity 0.2s;
}
.offline-bar__btn:hover:not(:disabled) { opacity: 1; }
.offline-bar__btn:disabled { opacity: 0.3; cursor: not-allowed; }

.offline-bar__icon {
    width: 14px;
    height: 14px;
    display: block;
}
.offline-bar__icon--spin {
    animation: spin 1s linear infinite;
}

@keyframes spin  { to { transform: rotate(360deg); } }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
</style>
