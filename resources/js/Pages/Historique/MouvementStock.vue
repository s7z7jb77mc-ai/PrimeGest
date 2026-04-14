<template>
  <div class="p-6 space-y-6">
    <!-- HEADER -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Historique des mouvements de stock</h1>
      <div class="flex gap-2">
        <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Retour
        </button>
      </div>
    </div>

    <!-- STATISTIQUES -->
    <div v-if="stats" class="grid grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded shadow">
        <h3 class="text-gray-500 text-sm font-semibold">Total Entrées</h3>
        <p class="text-2xl font-bold text-green-600">{{ stats.total_entrees }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h3 class="text-gray-500 text-sm font-semibold">Total Sorties</h3>
        <p class="text-2xl font-bold text-red-600">{{ stats.total_sorties }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h3 class="text-gray-500 text-sm font-semibold">Montant Entrées</h3>
        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.montant_entrees) }}</p>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h3 class="text-gray-500 text-sm font-semibold">Montant Sorties</h3>
        <p class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.montant_sorties) }}</p>
      </div>
    </div>

    <!-- FILTRES -->
    <div class="bg-white p-4 rounded shadow">
      <h2 class="text-lg font-semibold mb-3">Filtres</h2>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-semibold mb-1">Date début</label>
          <input type="date" v-model="filters.date_debut" @change="applyFilters" class="w-full border p-2 rounded"/>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Date fin</label>
          <input type="date" v-model="filters.date_fin" @change="applyFilters" class="w-full border p-2 rounded"/>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">Type</label>
          <select v-model="filters.type" @change="applyFilters" class="w-full border p-2 rounded">
            <option value="">Tous</option>
            <option value="entree">Entrée</option>
            <option value="sortie">Sortie</option>
          </select>
        </div>
      </div>
    </div>

    <!-- HISTORIQUE PAR JOUR -->
    <div class="space-y-4">
      <div v-for="dayArchive in archivesByDate" :key="dayArchive.date" class="bg-white rounded shadow overflow-hidden">
        <!-- En-tête du jour -->
        <div class="bg-blue-800 text-white p-4">
          <h3 class="text-lg font-semibold">
            {{ dayArchive.date_formatted }} - {{ dayArchive.jour_semaine }}
            <span class="text-sm ml-2 bg-blue-600 px-2 py-1 rounded">
              {{ dayArchive.mouvements.length }} mouvements
            </span>
          </h3>
        </div>

        <!-- Tableau des mouvements du jour -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="px-4 py-2 text-left text-sm font-semibold">Produit</th>
                <th class="px-4 py-2 text-left text-sm font-semibold">Type</th>
                <th class="px-4 py-2 text-center text-sm font-semibold">Quantité</th>
                <th class="px-4 py-2 text-right text-sm font-semibold">Prix Unitaire</th>
                <th class="px-4 py-2 text-right text-sm font-semibold">Prix Total</th>
                <th class="px-4 py-2 text-left text-sm font-semibold">Utilisateur</th>
                <th class="px-4 py-2 text-left text-sm font-semibold">Commentaire</th>
                <th class="px-4 py-2 text-left text-sm font-semibold">Heure</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="mouvement in dayArchive.mouvements" :key="mouvement.id" class="hover:bg-gray-50 transition">
                <td class="px-4 py-2">{{ mouvement.produit_nom }}</td>
                <td class="px-4 py-2">
                  <span :class="[
                    'px-2 py-1 rounded text-xs font-semibold',
                    mouvement.type === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                  ]">
                    {{ mouvement.type === 'entree' ? 'Entrée' : 'Sortie' }}
                  </span>
                </td>
                <td class="px-4 py-2 text-center">{{ mouvement.quantite }}</td>
                <td class="px-4 py-2 text-right">{{ mouvement.prix_unitaire }}</td>
                <td class="px-4 py-2 text-right font-semibold">{{ mouvement.prix_total }}</td>
                <td class="px-4 py-2 text-sm">{{ mouvement.user_name }}</td>
                <td class="px-4 py-2 text-sm">{{ mouvement.commentaire || '-' }}</td>
                <td class="px-4 py-2 text-sm">{{ mouvement.created_at.substring(11, 19) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Aucun résultat -->
      <div v-if="archivesByDate.length === 0" class="bg-white p-6 rounded shadow text-center text-gray-400">
        Aucun mouvement archivé pour le moment
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  archivesByDate: Array,
  stats: Object,
  filtres: Object
})

const filters = ref({
  date_debut: props.filtres?.date_debut || '',
  date_fin: props.filtres?.date_fin || '',
  type: props.filtres?.type || ''
})

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'CDF',
    minimumFractionDigits: 0,
  }).format(value || 0)
}

function applyFilters() {
  Inertia.get('/historique/mouvements-stock', filters.value, {
    replace: false,
    preserveScroll: true,
  })
}

function goBack() {
  Inertia.get('/mouvement-stocks')
}
</script>
