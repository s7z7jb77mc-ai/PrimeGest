<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">{{ title }}</h1>
      <div v-if="type !== 'facture' && type !== 'transfert'" class="flex gap-2">
        <button @click="downloadPdf(true)" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Imprimer PDF
        </button>
        <button @click="downloadPdf(false)" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Télécharger PDF
        </button>
      </div>
      <div v-else class="flex gap-2">
        <button @click="downloadFacturesPdf(true)" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Imprimer PDF
        </button>
        <button @click="downloadFacturesPdf(false)" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Télécharger PDF
        </button>
      </div>
    </div>

    <div class="bg-white shadow rounded p-4">
      <div v-if="type === 'bon_entree' || type === 'facture'" class="mb-4">
        <input
          v-model="searchTerm"
          type="text"
          :placeholder="type === 'bon_entree' ? 'Rechercher un fournisseur...' : 'Rechercher par numéro du client...'"
          class="border p-2 rounded w-full md:w-1/3"
        />
      </div>
      <div v-if="filteredArchives.length === 0" class="text-gray-400 text-center py-6">
        Aucune donnée archivée pour cette date
      </div>

      <div v-else-if="type === 'journal'">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Heure</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-left">Produit</th>
              <th class="px-3 py-2 text-left">Description</th>
              <th class="px-3 py-2 text-left">Utilisateur</th>
              <th class="px-3 py-2 text-right">Montant</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(a, idx) in archives" :key="idx">
              <td class="px-3 py-2">{{ formatDateOnly(a.date) }}</td>
              <td class="px-3 py-2">{{ a.heure || '-' }}</td>
              <td class="px-3 py-2">{{ a.type }}</td>
              <td class="px-3 py-2">{{ a.produit }}</td>
              <td class="px-3 py-2">{{ a.description }}</td>
              <td class="px-3 py-2">{{ a.user }}</td>
              <td class="px-3 py-2 text-right">{{ formatAmount(a.montant) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="type === 'mouvement_stock'">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Date & heure</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-left">Produit</th>
              <th class="px-3 py-2 text-right">Quantité</th>
              <th class="px-3 py-2 text-right">PU</th>
              <th class="px-3 py-2 text-right">PT</th>
              <th class="px-3 py-2 text-left">Utilisateur</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(a, idx) in archives" :key="idx">
              <td class="px-3 py-2">{{ formatDate(a.date) }}</td>
              <td class="px-3 py-2">{{ a.type }}</td>
              <td class="px-3 py-2">{{ a.produit }}</td>
              <td class="px-3 py-2 text-right">{{ a.quantite }}</td>
              <td class="px-3 py-2 text-right">{{ formatAmount(a.prix_unitaire) }}</td>
              <td class="px-3 py-2 text-right">{{ formatAmount(a.prix_total) }}</td>
              <td class="px-3 py-2">{{ a.user }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="type === 'caisse'">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Description</th>
              <th class="px-3 py-2 text-right">Entrée</th>
              <th class="px-3 py-2 text-right">Sortie</th>
              <th class="px-3 py-2 text-right">Solde</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(a, idx) in filteredArchives" :key="idx">
              <td class="px-3 py-2">{{ formatDate(a.date_operation) }}</td>
              <td class="px-3 py-2">{{ a.description || '-' }}</td>
              <td class="px-3 py-2 text-right">{{ formatAmount(a.entree) }}</td>
              <td class="px-3 py-2 text-right">{{ formatAmount(a.sortie) }}</td>
              <td class="px-3 py-2 text-right">{{ formatAmount(a.solde) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="type === 'transfert'">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Type</th>
              <th class="px-3 py-2 text-left">De</th>
              <th class="px-3 py-2 text-left">Vers</th>
              <th class="px-3 py-2 text-left">Produit / Montant</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(a, idx) in filteredArchives" :key="idx">
              <td class="px-3 py-2">{{ formatDate(a.date_operation) }}</td>
              <td class="px-3 py-2">{{ a.type_transfert === 'stock' ? 'Stock' : 'Caisse' }}</td>
              <td class="px-3 py-2">{{ a.from_succursale || '-' }}</td>
              <td class="px-3 py-2">{{ a.to_succursale || '-' }}</td>
              <td class="px-3 py-2">
                <span v-if="a.type_transfert === 'stock'">{{ a.produit || '-' }} ({{ a.quantite || 0 }})</span>
                <span v-else>{{ formatAmount(a.montant) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="type === 'bon_entree'">
        <div class="flex items-center gap-3 mb-3">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="selectAll" @change="toggleAll" />
            Tout sélectionner
          </label>
          <span class="text-sm text-gray-600">{{ selectedCount }} sélectionnée(s)</span>
        </div>
        <div v-for="(b, idx) in filteredArchives" :key="idx" class="border border-gray-200 rounded p-4 mb-4">
          <div class="flex items-center justify-between mb-2">
            <label class="flex items-center gap-2">
              <input type="checkbox" :value="b.reference_id" v-model="selectedIds" />
              <span class="font-semibold">Bon d’entrée {{ b.numero || '-' }}</span>
            </label>
            <div class="text-sm text-gray-600">{{ formatDate(b.date_bon) }}</div>
          </div>
          <div class="text-sm text-gray-600 mb-2">Fournisseur: {{ b.fournisseur || '-' }}</div>
          <table class="min-w-full divide-y divide-gray-200 mb-3">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Désignation</th>
                <th class="px-3 py-2 text-right">Quantité</th>
                <th class="px-3 py-2 text-right">PU</th>
                <th class="px-3 py-2 text-right">PT</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(l, j) in b.lignes" :key="j">
                <td class="px-3 py-2">{{ l.designation }}</td>
                <td class="px-3 py-2 text-right">{{ l.quantite }}</td>
                <td class="px-3 py-2 text-right">{{ formatAmount(l.prix_unitaire) }}</td>
                <td class="px-3 py-2 text-right">{{ formatAmount(l.total) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="text-right text-sm">
            <div class="font-semibold">Total: {{ formatAmount(b.total) }}</div>
          </div>
        </div>
      </div>

      <div v-else>
        <div class="flex items-center gap-3 mb-3">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" v-model="selectAll" @change="toggleAll" />
            Tout sélectionner
          </label>
          <span class="text-sm text-gray-600">{{ selectedCount }} sélectionnée(s)</span>
        </div>
          <div v-for="(f, idx) in filteredArchives" :key="idx" class="border border-gray-200 rounded p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
              <label class="flex items-center gap-2">
                <input type="checkbox" :value="f.reference_id" v-model="selectedIds" />
                <span class="font-semibold">Facture {{ f.numero || '-' }}</span>
              </label>
              <div class="text-sm text-gray-600">{{ formatDate(f.date_facture) }}</div>
            </div>
            <div class="text-sm text-gray-600 mb-2">Client: {{ f.client || '-' }}</div>
            <table class="min-w-full divide-y divide-gray-200 mb-3">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left">Désignation</th>
                  <th class="px-3 py-2 text-right">Quantité</th>
                <th class="px-3 py-2 text-right">PU</th>
                <th class="px-3 py-2 text-right">PT</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(l, j) in f.lignes" :key="j">
                <td class="px-3 py-2">{{ l.designation }}</td>
                <td class="px-3 py-2 text-right">{{ l.quantite }}</td>
                <td class="px-3 py-2 text-right">{{ formatAmount(l.prix_ttc) }}</td>
                <td class="px-3 py-2 text-right">{{ formatAmount(l.total) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="text-right text-sm space-y-1">
            <div>PV HT: {{ formatAmount(f.total_ht) }}</div>
            <div>TVA: {{ formatAmount(f.total_tva) }}</div>
            <div class="font-semibold">PV TTC: {{ formatAmount(f.total_ttc) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">

import { computed, ref } from 'vue'

const props = defineProps({
  type: String,
  date: String,
  archives: { type: Array, default: () => [] },
})

const selectedIds = ref([])
const selectAll = ref(false)
const selectedCount = computed(() => selectedIds.value.length)
const searchTerm = ref('')

const filteredArchives = computed(() => {
  const term = String(searchTerm.value || '').trim().toLowerCase()
  if (!term) return props.archives || []
  if (props.type === 'bon_entree') {
    return (props.archives || []).filter(a =>
      String(a.fournisseur || '').toLowerCase().includes(term)
    )
  }
  if (props.type === 'facture') {
    return (props.archives || []).filter(a => {
      const phone = String(a.client_phone || '')
      const client = String(a.client || '').toLowerCase()
      return phone.includes(term) || client.includes(term)
    })
  }
  return props.archives || []
})

const titleMap = {
  journal: 'Journal',
  mouvement_stock: 'Mouvement Stock',
  facture: 'Facture',
  bon_entree: 'Bon d’entrée',
  caisse: 'Caisse',
  transfert: 'Transferts',
}

const title = `${titleMap[props.type] || 'Archive'} du ${props.date}`

function downloadPdf(inline) {
  logAction(inline ? 'print' : 'download')
  if (props.type === 'bon_entree') {
    const ids = selectedIds.value.filter(Boolean)
    const query = ids.length ? `?ids=${encodeURIComponent(ids.join(','))}${inline ? '&inline=1' : ''}` : (inline ? '?inline=1' : '')
    const url = `/archives/${props.type}/${props.date}/pdf${query}`
    window.open(url, '_blank')
    return
  }
  const url = `/archives/${props.type}/${props.date}/pdf${inline ? '?inline=1' : ''}`
  window.open(url, '_blank')
}

function downloadFacturesPdf(inline) {
  const ids = selectedIds.value.filter(Boolean)
  logAction(inline ? 'print' : 'download')
  const query = ids.length ? `?ids=${encodeURIComponent(ids.join(','))}${inline ? '&inline=1' : ''}` : (inline ? '?inline=1' : '')
  const url = `/archives/factures/${props.date}/pdf${query}`
  window.open(url, '_blank')
}

function toggleAll() {
  if (selectAll.value) {
    selectedIds.value = (filteredArchives.value || []).map(a => a.reference_id).filter(Boolean)
  } else {
    selectedIds.value = []
  }
}

function formatAmount(value) {
  const num = Number(value || 0)
  return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    if (!isNaN(d.getTime())) {
      const fmt = new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'Africa/Lubumbashi',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      })
      return fmt.format(d)
    }
  } catch (_e) {}
  return String(dateStr).replace('T', ' ').substring(0, 19)
}

function formatDateOnly(dateStr) {
  if (!dateStr) return ''
  return String(dateStr).substring(0, 10)
}

async function logAction(action) {
  const reportType = props.type === 'bon_entree' ? 'bon_entree' : props.type
  const tokenEl = document.querySelector('meta[name="csrf-token"]')
  const csrf = tokenEl ? tokenEl.getAttribute('content') : null
  try {
    await fetch('/rapport/log', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      },
      body: JSON.stringify({
        action,
        report_type: reportType,
        report_date: props.date,
      }),
      credentials: 'same-origin',
    })
  } catch (_e) {
    // silent
  }
}
</script>
