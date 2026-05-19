<template>
  <div class="p-6">
    <!-- HEADER IMPRIMABLE -->
    <div class="mb-6 print:mb-4">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Journaux</h1>
        <div class="flex gap-2 print:hidden">
          <button @click="printPage" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Imprimer
          </button>
          <button @click="downloadPdf" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            PDF
          </button>
          <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            Retour
          </button>
        </div>
      </div>

      <div class="bg-gray-50 p-4 rounded">
        <p class="text-gray-700"><strong>Date:</strong> {{ date }} ({{ jour_semaine }})</p>
        <p class="text-gray-700"><strong>Nombre d'entrées:</strong> {{ mouvements.length }}</p>
      </div>
    </div>

    <!-- STATISTIQUES -->
    <div class="grid grid-cols-3 gap-4 mb-6 print:gap-2">
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Total Entrées</h3>
        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(entrees) }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Total Sorties</h3>
        <p class="text-2xl font-bold text-red-600">{{ formatCurrency(sorties) }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Solde</h3>
        <p class="text-2xl font-bold" :class="total >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(total) }}</p>
      </div>
    </div>

    <!-- TABLEAU -->
    <div class="bg-white rounded shadow overflow-hidden">
      <table class="w-full border-collapse text-sm">
        <thead class="bg-blue-800 text-white">
          <tr>
            <th class="px-3 py-2 text-left">Produit</th>
            <th class="px-3 py-2 text-left">Type</th>
            <th class="px-3 py-2 text-right">Montant</th>
            <th class="px-3 py-2 text-left">Description</th>
            <th class="px-3 py-2 text-left">Utilisateur</th>
            <th class="px-3 py-2 text-left">Date/Heure</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="mouvement in mouvements" :key="mouvement.id" class="hover:bg-gray-50 print:hover:bg-white">
            <td class="px-3 py-2">{{ mouvement.produit?.nom || 'N/A' }}</td>
            <td class="px-3 py-2">
              <span :class="[
                'px-2 py-1 rounded text-xs font-semibold',
                mouvement.type === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ mouvement.type === 'entree' ? 'Entrée' : 'Sortie' }}
              </span>
            </td>
            <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(mouvement.montant) }}</td>
            <td class="px-3 py-2 text-xs">{{ mouvement.description || '-' }}</td>
            <td class="px-3 py-2 text-xs">{{ mouvement.user?.name || 'Inconnu' }}</td>
            <td class="px-3 py-2 text-xs">{{ formatDateTime(mouvement.dateHeure_operation) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- FOOTER -->
    <div class="mt-6 text-center text-gray-500 text-sm print:mt-4">
      <p>Document généré le {{ getCurrentDate() }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const _props = defineProps({
  date: String,
  jour_semaine: String,
  mouvements: Array,
  entrees: Number,
  sorties: Number,
  total: Number,
})

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'CDF',
    minimumFractionDigits: 0,
  }).format(value || 0)
}

function formatDateTime(dateTime) {
  const date = new Date(dateTime)
  return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

function getCurrentDate() {
  const now = new Date()
  return now.toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function printPage() {
  window.print()
}

function downloadPdf() {
  window.alert('Fonction PDF en cours de développement. Utilisez Imprimer et enregistrer en PDF depuis votre navigateur.')
}

function goBack() {
  router.get('/archives/journaux')
}
</script>

<style scoped>
@media print {
  @page {
    margin: 0.5cm;
  }
  
  body {
    margin: 0;
    padding: 0;
  }

  .print\:hidden {
    display: none !important;
  }

  .print\:mb-4 {
    margin-bottom: 1rem !important;
  }

  .print\:gap-2 {
    gap: 0.5rem !important;
  }

  .print\:shadow-none {
    box-shadow: none !important;
  }

  .print\:border {
    border: 1px solid #e5e7eb !important;
  }

  .print\:hover\:bg-white:hover {
    background-color: white !important;
  }

  table {
    page-break-inside: avoid;
  }

  tr {
    page-break-inside: avoid;
  }
}
</style>
