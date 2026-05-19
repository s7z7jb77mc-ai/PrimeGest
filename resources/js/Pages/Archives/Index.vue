<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Archives</h1>
      <div class="flex gap-2">
        <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Retour
        </button>
      </div>
    </div>

    <!-- Onglets -->
    <div class="bg-white shadow rounded">
      <div class="flex border-b">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="changeType(tab.value)"
          :class="[
            'px-6 py-3 font-medium text-sm',
            type === tab.value ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-gray-800'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>
      <div class="p-4 flex items-center gap-2">
        <label class="text-sm text-gray-700">Année:</label>
        <select v-model="selectedYear" class="border rounded px-3 py-2 text-sm" @change="reloadYear">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <!-- Grille 3x4 des mois -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div
        v-for="(m, idx) in monthNames"
        :key="idx"
        class="bg-white p-4 rounded shadow border cursor-pointer"
        :class="months[idx + 1] ? 'border-blue-200 hover:border-blue-500' : 'border-gray-200 opacity-70'"
        @click="openMonth(idx + 1)"
      >
        <div class="font-semibold">{{ m }}</div>
        <div class="text-sm text-gray-500 mt-1">
          📄 {{ months[idx + 1] || 0 }} archivés
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  type: { type: String, default: 'journal' },
  year: { type: Number, default: new Date().getFullYear() },
  years: { type: Array, default: () => [] },
  months: { type: Object, default: () => ({}) },
})

const tabs = [
  { label: 'Journal', value: 'journal' },
  { label: 'Mouvement stock', value: 'mouvement_stock' },
  { label: 'Factures', value: 'facture' },
  { label: 'Bon d’entrée', value: 'bon_entree' },
  { label: 'Caisse', value: 'caisse' },
  { label: 'Transferts', value: 'transfert' },
]

const monthNames = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
]

const selectedYear = ref(props.year)
const type = props.type
const months = props.months
const years = (props.years && props.years.length) ? props.years : [props.year]

function changeType(t) {
  router.get('/archives', { type: t, year: selectedYear.value })
}

function reloadYear() {
  router.get('/archives', { type, year: selectedYear.value })
}

function openMonth(month) {
  router.get(`/archives/${type}/${selectedYear.value}/${month}`)
}

function goBack() {
  router.get('/dashboard')
}
</script>
