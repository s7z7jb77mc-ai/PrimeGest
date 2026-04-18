<script setup lang="ts">
// removed unused imports
import { Inertia } from '@inertiajs/inertia'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
defineOptions({ layout: AppDashboardLayout })

// Props reçues depuis Inertia
const props = defineProps({
  factures: Array,   // liste des factures
  parametres: Object // contient la devise
})

// Aller au détail d'une facture
function showFacture(id) {
  Inertia.get(`/factures/${id}`)
}

// Format affichage date
function formatDate(dt) {
  return dt ? dt.substring(0, 19) : ''
}

function goArchives() {
  Inertia.get('/archives/factures')
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Liste des factures</h1>
      <button @click="goArchives" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700">
        Archives
      </button>
    </div>

    <div class="bg-white shadow rounded p-4 overflow-x-auto">
    <div class="overflow-x-auto -mx-4 sm:mx-0">
      <table class="min-w-full table-auto divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-3 py-2 text-left">N° Facture</th>
            <th class="px-3 py-2 text-left">Date</th>
            <th class="px-3 py-2 text-right">Total TTC</th>
            <th class="px-3 py-2 text-right">Cash</th>
            <th class="px-3 py-2 text-right">Échange</th>
            <th class="px-3 py-2 text-left">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="f in factures" :key="f.id" class="hover:bg-gray-50 transition">
            <td class="px-3 py-2">{{ f.numero }}</td>
            <td class="px-3 py-2">{{ formatDate(f.date_facture) }}</td>
            <td class="px-3 py-2 text-right">{{ f.total_montant.toFixed(2) }} {{ props.parametres?.devise || '' }}</td>
            <td class="px-3 py-2 text-right">{{ f.cash.toFixed(2) }} {{ props.parametres?.devise || '' }}</td>
            <td class="px-3 py-2 text-right">{{ (f.cash - f.total_montant).toFixed(2) }} {{ props.parametres?.devise || '' }}</td>
            <td class="px-3 py-2">
              <button @click="showFacture(f.id)" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Voir</button>
            </td>
          </tr>
          <tr v-if="factures.length === 0">
            <td colspan="6" class="p-2 text-center text-gray-500">Aucune facture</td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </div>
</template>
