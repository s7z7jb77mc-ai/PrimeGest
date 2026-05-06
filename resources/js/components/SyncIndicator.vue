<template>
    <div
        class="sync-indicator flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-all"
        :class="{
            'bg-red-100 text-red-700': sync.status === 'offline',
            'bg-blue-100 text-blue-700': sync.status === 'syncing',
            'bg-yellow-100 text-yellow-700': sync.status === 'pending',
            'bg-green-100 text-green-700': sync.status === 'synced',
        }"
        :title="sync.pendingCount > 0 ? `${sync.pendingCount} opération(s) en attente de synchronisation. Elles seront envoyées automatiquement dès que la connexion revient.` : undefined"
    >
        <span
            class="size-1.5 rounded-full"
            :class="{
                'bg-red-500': sync.status === 'offline',
                'animate-pulse bg-blue-500': sync.status === 'syncing',
                'bg-yellow-500': sync.status === 'pending',
                'bg-green-500': sync.status === 'synced',
            }"
        />
        <span>{{ sync.statusLabel }}</span>
    </div>
</template>

<script setup lang="ts">
import { useSyncStore } from '@/stores/useSyncStore';

const sync = useSyncStore();
</script>
