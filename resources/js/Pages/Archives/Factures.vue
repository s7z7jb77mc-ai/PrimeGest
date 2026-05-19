<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Archives des factures</h1>
      <div class="flex gap-2">
        <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Retour
        </button>
      </div>
    </div>

    <div class="bg-white p-4 rounded shadow">
      <div class="flex items-center gap-2">
        <input
          v-model="clientPhone"
          @keyup.enter="applySearch"
          type="text"
          placeholder="Rechercher par numéro du client..."
          class="border p-2 rounded w-full md:w-1/3"
        />
        <button @click="applySearch" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Rechercher
        </button>
      </div>
    </div>

    <div v-if="factures.length > 0" class="space-y-4">
      <div v-for="archive in factures" :key="archive.date" class="bg-white p-6 rounded shadow">
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-lg font-semibold">{{ archive.date_formatted }}</h3>
            <p class="text-sm text-gray-500">{{ archive.count }} facture(s)</p>
          </div>
          <div class="text-right">
            <p class="text-sm text-gray-500">Total TTC</p>
            <p class="font-semibold">{{ formatAmount(archive.total_ttc) }} {{ devise }}</p>
          </div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Numéro</th>
                <th class="px-3 py-2 text-left">Date</th>
                <th class="px-3 py-2 text-right">Total TTC</th>
                <th class="px-3 py-2 text-left">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="f in archive.factures" :key="f.id">
                <td class="px-3 py-2">{{ f.numero }}</td>
                <td class="px-3 py-2">{{ formatDateGmt2(f.date_facture) }}</td>
                <td class="px-3 py-2 text-right">{{ formatAmount(f.total_ttc) }} {{ devise }}</td>
                <td class="px-3 py-2">
                  <button @click="showFacture(f.id)" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Voir
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-else class="bg-white p-6 rounded shadow text-center text-gray-400">
      Aucune facture archivée
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  factures: { type: Array, default: () => [] },
  devise: { type: String, default: 'CDF' },
  filters: { type: Object, default: () => ({}) }
})

const clientPhone = ref(props.filters?.client_phone || '')

function goBack() {
  router.get('/factures')
}

function showFacture(id) {
  router.get(`/factures/${id}`)
}

function applySearch() {
  router.get('/archives/factures', { client_phone: clientPhone.value }, { preserveState: true, preserveScroll: true })
}

function formatAmount(value) {
  const num = Number(value || 0)
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDateGmt2(dateStr) {
  if (!dateStr) return ''
  const raw = String(dateStr).trim().replace(' ', 'T')
  const parts = raw.split('T')
  if (!parts[0]) return dateStr
  const [y, m, d] = parts[0].split('-').map(Number)
  const [hh = 0, mm = 0, ss = 0] = (parts[1] || '00:00:00').split(':').map(Number)
  const utc = Date.UTC(y, (m || 1) - 1, d || 1, hh || 0, mm || 0, ss || 0)
  const gmt2 = new Date(utc + 2 * 60 * 60 * 1000)
  const dd = String(gmt2.getUTCDate()).padStart(2, '0')
  const mmStr = String(gmt2.getUTCMonth() + 1).padStart(2, '0')
  const yyyy = gmt2.getUTCFullYear()
  const hhStr = String(gmt2.getUTCHours()).padStart(2, '0')
  const minStr = String(gmt2.getUTCMinutes()).padStart(2, '0')
  return `${dd}/${mmStr}/${yyyy} ${hhStr}:${minStr}`
}
</script>
