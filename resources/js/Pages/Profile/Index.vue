<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Profil utilisateur</h1>
      <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        Retour
      </button>
    </div>

    <div class="bg-white shadow rounded p-6 space-y-4">
      <div class="flex items-center gap-4">
        <div class="h-16 w-16 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xl">
          {{ initiales }}
        </div>
        <div>
          <div class="text-lg font-semibold">{{ user?.name || '-' }}</div>
          <div class="text-sm text-gray-600">{{ user?.email || '-' }}</div>
          <div class="text-xs text-gray-500 capitalize">{{ user?.role || '-' }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <div class="text-sm text-gray-500">Téléphone</div>
          <div class="font-medium">{{ user?.employe?.telephone || '-' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Poste</div>
          <div class="font-medium">{{ user?.employe?.poste || '-' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Entreprise</div>
          <div class="font-medium">{{ parametres?.nom_entreprise || '-' }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-500">Adresse</div>
          <div class="font-medium">{{ parametres?.adresse || '-' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  user: { type: Object, default: () => ({}) },
  parametres: { type: Object, default: () => ({}) },
})

const initiales = computed(() => {
  const name = String(props.user?.name || '').trim()
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()
})

function goBack() {
  router.get('/dashboard')
}
</script>
