<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-2xl font-bold">
          {{ entityType === 'client' ? 'Client' : 'Fournisseur' }} : {{ entityName }}
        </h1>
        <p class="text-sm text-gray-500">Historique complet</p>
      </div>
      <div class="flex gap-2">
        <button type="button" @click="download" class="px-3 py-2 bg-blue-600 text-white rounded">Télécharger</button>
        <button type="button" @click="printPage" class="px-3 py-2 bg-green-600 text-white rounded">Imprimer</button>
        <button type="button" @click="goBack" class="px-3 py-2 bg-gray-600 text-white rounded">Retour</button>
      </div>
    </div>

    <div class="bg-white shadow rounded p-4 space-y-4">
      <div class="border-b pb-4">
        <div class="font-semibold text-lg">{{ parametres?.nom_entreprise || 'Entreprise' }}</div>
        <div class="text-sm text-gray-600">{{ parametres?.adresse || '-' }}</div>
        <div class="text-sm text-gray-600">{{ parametres?.telephone || '-' }}</div>
      </div>

      <div class="border-b pb-4">
        <div class="font-semibold">
          {{ entityType === 'client' ? 'Client' : 'Fournisseur' }} : {{ entityName }}
        </div>
        <div class="text-sm text-gray-600">Téléphone : {{ entityInfo?.telephone || '-' }}</div>
        <div class="text-sm text-gray-600">Adresse : {{ entityInfo?.adresse || '-' }}</div>
      </div>

      <div v-if="summary.length" class="space-y-4">
        <div v-for="(row, idx) in summary" :key="idx" class="border rounded p-4">
          <div class="font-semibold mb-2">{{ row.label }}</div>
          <ul class="text-sm space-y-1">
            <li>Achats : {{ formatAmount(row.achat) }} {{ devise }}</li>
            <li v-if="entityType === 'client'">Créance : {{ formatAmount(row.creance) }} {{ devise }}</li>
            <li v-else>Dette : {{ formatAmount(row.dette) }} {{ devise }}</li>
            <li>Paiement : {{ formatAmount(row.paiement) }} {{ devise }}</li>
            <li>Réduction obtenue : {{ formatAmount(row.reduction) }} {{ devise }}</li>
            <li>Récupération : {{ row.recuperation_date || '-' }}</li>
          </ul>
        </div>
      </div>
      <div v-else class="text-center text-gray-400">Aucune donnée disponible</div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  entityType: String,
  entityName: String,
  entityId: [String, Number],
  entityInfo: { type: Object, default: () => ({}) },
  summary: { type: Array, default: () => [] },
  devise: { type: String, default: 'CDF' },
  parametres: { type: Object, default: () => ({}) },
})

function formatAmount(value) {
  const n = Number(value || 0)
  return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function printPage() {
  const url = buildPdfUrl(true)
  window.open(url, '_blank')
}

function download() {
  const url = buildPdfUrl(false)
  window.open(url, '_blank')
}

function buildPdfUrl(inline) {
  const id = props.entityId
  if (!id) return '#'
  const base = props.entityType === 'client'
    ? `/creances-dettes/clients/${id}/pdf`
    : `/creances-dettes/fournisseurs/${id}/pdf`
  return inline ? `${base}?inline=1` : base
}

function goBack() {
  router.get('/creances-dettes')
}
</script>
