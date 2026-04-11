<script setup>
import { router } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

const props = defineProps({
  employesCount: { type: Number, default: 0 },
  fichesCount: { type: Number, default: 0 },
})
const t = _t
const lang = useLang()

function goDashboard() {
  router.get('/dashboard')
}
</script>

<template>
  <div class="p-6 space-y-6" :key="lang">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">{{ t('human_resources') }}</h1>
      <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        {{ t('dashboard') }}
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white shadow rounded p-6">
        <h2 class="text-lg font-semibold mb-2">Employés</h2>
        <p class="text-sm text-gray-600">Total: {{ props.employesCount }}</p>
        <button type="button" @click="router.get('/employes')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">
          Gérer les employés
        </button>
      </div>

      <div class="bg-white shadow rounded p-6">
        <h2 class="text-lg font-semibold mb-2">Fiches de paie</h2>
        <p class="text-sm text-gray-600">Total: {{ props.fichesCount }}</p>
        <button type="button" @click="router.get('/fiches')" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded">
          Gérer les fiches de paie
        </button>
      </div>
    </div>
  </div>
</template>
