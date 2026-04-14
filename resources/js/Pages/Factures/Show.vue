<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  facture: Object,
  parametres: Object
})

const devise = computed(() => props.parametres?.devise || '')
const logoUrl = computed(() => props.parametres?.logo_url || '')
const logoPosition = computed(() => props.parametres?.logo_position || 'left')
const tvaRate = computed(() => Number(props.facture?.tva ?? props.parametres?.tva ?? 0))

const totalTTC = computed(() => Number(props.facture?.total_ttc ?? props.facture?.total_montant ?? 0))
const totalHT = computed(() => tvaRate.value > 0 ? totalTTC.value / (1 + tvaRate.value / 100) : totalTTC.value)
const totalTVA = computed(() => totalTTC.value - totalHT.value)

function goBack() {
  router.get('/mouvement-stocks')
}

function printFacture() {
  window.print()
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

<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Facture {{ facture.numero }}</h1>
      <div class="flex gap-2">
        <button type="button" @click="printFacture" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">Imprimer</button>
        <button type="button" @click="goBack" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700">Retour</button>
      </div>
    </div>

    <!-- En-tête -->
    <div class="bg-white shadow rounded p-4 relative">
      <div v-if="logoUrl" class="absolute top-4 left-4 right-4" :class="logoPosition === 'center' ? 'text-center' : (logoPosition === 'right' ? 'text-right' : 'text-left')">
        <img :src="logoUrl" class="h-12 inline-block" />
      </div>
      <div class="flex flex-col md:flex-row md:justify-between gap-4 pt-16">
        <div>
          <p class="text-lg font-bold">
            {{ parametres?.nom_entreprise || facture.entreprise?.name || 'Entreprise' }}
            <span v-if="facture.succursale?.nom">({{ facture.succursale.nom }})</span>
          </p>
          <p class="text-sm text-gray-700">
            {{ facture.succursale?.adresse || parametres?.adresse || '-' }}
          </p>
          <p class="text-sm text-gray-700">Tél: {{ parametres?.telephone || '-' }}</p>
          <p class="text-sm text-gray-700">Email: {{ parametres?.email || '-' }}</p>
          <p class="text-sm text-gray-700">ID National: {{ parametres?.identifiant_national || '-' }}</p>
          <p class="text-sm text-gray-700">RCCM: {{ parametres?.rccm || '-' }}</p>
        </div>
        <div class="text-sm text-gray-700">
          <p><strong>Date:</strong> {{ formatDateGmt2(facture.date_facture) }}</p>
          <p><strong>Numéro:</strong> {{ facture.numero }}</p>
          <p><strong>TVA:</strong> {{ tvaRate.toFixed(2) }}%</p>
          <p v-if="facture.client_nom || facture.client_telephone" class="mt-2">
            <strong>Client:</strong> {{ facture.client_nom || '-' }}<br>
            <strong>Téléphone:</strong> {{ facture.client_telephone || '-' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Corps facture -->
    <div class="bg-white shadow rounded p-4 overflow-x-auto">
      <h2 class="text-lg font-semibold mb-2">Détails</h2>
      <table class="min-w-full table-auto divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-3 py-2 text-left">Qte</th>
            <th class="px-3 py-2 text-left">Désignation</th>
            <th class="px-3 py-2 text-right">PU</th>
            <th class="px-3 py-2 text-right">PT</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="l in facture.lignes" :key="l.id">
            <td class="px-3 py-2">{{ l.quantite }}</td>
            <td class="px-3 py-2">{{ l.designation }}</td>
            <td class="px-3 py-2 text-right">{{ Number(l.prix_ttc || 0).toFixed(2) }} {{ devise }}</td>
            <td class="px-3 py-2 text-right">{{ Number(l.total || (l.quantite * l.prix_ttc)).toFixed(2) }} {{ devise }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Totaux -->
    <div class="bg-white shadow rounded p-4 space-y-2 text-right">
      <div><strong>Prix de vente hors taxe:</strong> {{ totalHT.toFixed(2) }} {{ devise }}</div>
      <div><strong>Taxe (TVA):</strong> {{ totalTVA.toFixed(2) }} {{ devise }}</div>
      <div><strong>Prix de vente TTC:</strong> {{ totalTTC.toFixed(2) }} {{ devise }}</div>
    </div>

    <div class="bg-white shadow rounded p-4">
      <p class="text-sm text-gray-700 text-center">{{ parametres?.message_remerciement || 'Merci pour votre confiance.' }}</p>
    </div>
  </div>
</template>

<style scoped>
@media print {
  button {
    display: none !important;
  }
}
</style>
