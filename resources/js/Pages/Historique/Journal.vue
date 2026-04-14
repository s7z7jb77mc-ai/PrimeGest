<template>
  <div class="min-h-screen bg-gray-50">
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900">Historique des Journaux</h1>
          <p class="mt-2 text-gray-600">Consultez l'historique complet des opérations archivées</p>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Total Montant</div>
            <div class="mt-2 text-3xl font-bold text-blue-600">{{ formatCurrency(stats.total_montant) }}</div>
          </div>
          <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Nombre d'Opérations</div>
            <div class="mt-2 text-3xl font-bold text-purple-600">{{ stats.nombre_operations }}</div>
          </div>
          <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Montant Moyen</div>
            <div class="mt-2 text-3xl font-bold text-indigo-600">
              {{ formatCurrency(stats.total_montant / (stats.nombre_operations || 1)) }}
            </div>
          </div>
        </div>

        <!-- Détail par type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
          <div v-for="(data, type) in stats.par_type" :key="type" class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 capitalize">{{ type }}</div>
            <div class="mt-2 grid grid-cols-2 gap-4">
              <div>
                <div class="text-xs text-gray-600">Nombre</div>
                <div class="text-2xl font-bold text-gray-900">{{ data.count }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-600">Montant</div>
                <div class="text-2xl font-bold text-gray-900">{{ formatCurrency(data.montant) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
          <h2 class="text-lg font-semibold mb-4">Filtres</h2>
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date Début</label>
              <input 
                v-model="filtres.date_debut" 
                type="date" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date Fin</label>
              <input 
                v-model="filtres.date_fin" 
                type="date" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
              <select 
                v-model="filtres.type" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500"
              >
                <option value="tous">Tous</option>
                <option value="vente">Vente</option>
                <option value="paiement">Paiement</option>
                <option value="achat">Achat</option>
                <option value="autre">Autre</option>
              </select>
            </div>
            <div class="flex items-end">
              <button 
                type="submit"
                class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
              >
                Appliquer
              </button>
            </div>
          </form>
        </div>

        <!-- Tableau des journaux -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Heure</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="journal in journaux.data" :key="journal.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDateTime(journal.dateHeure_operation) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                      {{ journal.type }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-900">{{ journal.description || '-' }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ formatCurrency(journal.montant) }}</td>
                </tr>
                <tr v-if="journaux.data.length === 0">
                  <td colspan="4" class="px-6 py-4 text-center text-gray-500">Aucun journal trouvé</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="bg-gray-50 px-6 py-4 flex items-center justify-between">
            <div class="text-sm text-gray-600">
              Affichage {{ journaux.from }} à {{ journaux.to }} sur {{ journaux.total }} résultats
            </div>
            <div class="flex gap-2">
              <Link 
                v-if="journaux.prev_page_url"
                :href="journaux.prev_page_url"
                class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Précédent
              </Link>
              <Link 
                v-if="journaux.next_page_url"
                :href="journaux.next_page_url"
                class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Suivant
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  journaux: Object,
  stats: Object,
  filtres: Object,
})

const filtres = ref({
  date_debut: props.filtres.date_debut || '',
  date_fin: props.filtres.date_fin || '',
  type: props.filtres.type || 'tous',
})

const applyFilters = () => {
  router.get('/historique/journaux', filtres.value)
}

const formatDateTime = (dateTime) => {
  const date = new Date(dateTime)
  return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const formatCurrency = (value) => {
  if (!value) return '0.00 €'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(value)
}
</script>
