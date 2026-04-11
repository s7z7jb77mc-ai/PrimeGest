<template>
  <div class="p-6">
    <!-- HEADER IMPRIMABLE -->
    <div class="mb-6 print:mb-4">
      <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Mouvements de stock</h1>
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
        <p class="text-gray-700"><strong>Date:</strong> {{ date_formatted }} ({{ jour_semaine }})</p>
        <p class="text-gray-700"><strong>Nombre de mouvements:</strong> {{ mouvements.length }}</p>
      </div>
    </div>

    <!-- STATISTIQUES -->
    <div class="grid grid-cols-4 gap-4 mb-6 print:gap-2">
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Total Entrées</h3>
        <p class="text-2xl font-bold text-green-600">{{ total_entrees }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Total Sorties</h3>
        <p class="text-2xl font-bold text-red-600">{{ total_sorties }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Montant Entrées</h3>
        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(montant_entrees) }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow print:shadow-none print:border">
        <h3 class="text-gray-500 text-sm font-semibold">Montant Sorties</h3>
        <p class="text-2xl font-bold text-red-600">{{ formatCurrency(montant_sorties) }}</p>
      </div>
    </div>

    <!-- TABLEAU -->
    <div class="bg-white rounded shadow overflow-hidden">
      <table class="w-full border-collapse text-sm">
        <thead class="bg-blue-800 text-white">
          <tr>
            <th class="px-3 py-2 text-left">Produit</th>
            <th class="px-3 py-2 text-left">Type</th>
            <th class="px-3 py-2 text-center">Quantité</th>
            <th class="px-3 py-2 text-right">Prix Unit.</th>
            <th class="px-3 py-2 text-right">Prix Total</th>
            <th class="px-3 py-2 text-left">Utilisateur</th>
            <th class="px-3 py-2 text-left">Heure</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="mouvement in mouvements" :key="mouvement.id" class="hover:bg-gray-50 print:hover:bg-white">
            <td class="px-3 py-2">{{ mouvement.produit_nom }}</td>
            <td class="px-3 py-2">
              <span :class="[
                'px-2 py-1 rounded text-xs font-semibold',
                mouvement.type === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ mouvement.type === 'entree' ? 'E' : 'S' }}
              </span>
            </td>
            <td class="px-3 py-2 text-center">{{ mouvement.quantite }}</td>
            <td class="px-3 py-2 text-right">{{ mouvement.prix_unitaire }}</td>
            <td class="px-3 py-2 text-right font-semibold">{{ mouvement.prix_total }}</td>
            <td class="px-3 py-2 text-xs">{{ mouvement.user_name }}</td>
            <td class="px-3 py-2 text-xs">{{ mouvement.created_at.substring(11, 19) }}</td>
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

<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  date: String,
  date_formatted: String,
  jour_semaine: String,
  mouvements: Array,
  total_entrees: Number,
  total_sorties: Number,
  montant_entrees: Number,
  montant_sorties: Number,
})

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'CDF',
    minimumFractionDigits: 0,
  }).format(value || 0)
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
  // Utiliser une bibliothèque comme jsPDF ou html2pdf
  window.alert('Fonction PDF en cours de développement. Utilisez Imprimer et enregistrer en PDF depuis votre navigateur.')
}

function goBack() {
  router.get('/archives/mouvements-stock')
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
