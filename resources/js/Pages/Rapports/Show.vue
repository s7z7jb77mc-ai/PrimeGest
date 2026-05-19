<template>
  <div class="min-h-screen bg-gray-50">
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Rapport {{ capitalizeType(rapport.type) }}</h1>
            <p class="mt-2 text-gray-600">
              <span v-if="rapport.type === 'journalier'">
                {{ formatDate(rapport.date_debut) }}
              </span>
              <span v-else>
                Du {{ formatDate(rapport.date_debut) }} au {{ formatDate(rapport.date_fin) }}
              </span>
            </p>
          </div>
          <Link 
            href="/rapport"
            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition"
          >
            Retour aux Rapports
          </Link>
        </div>

        <!-- Résumé -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
          <h2 class="text-lg font-semibold mb-6">Résumé</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="(value, key) in resume" :key="key" class="border-l-4 border-blue-500 pl-4">
              <p class="text-sm text-gray-600">{{ formatKey(key) }}</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatValue(key, value) }}</p>
            </div>
          </div>
        </div>

        <!-- Contenu du rapport -->
        <div v-if="rapport.type === 'journalier'" class="space-y-8">
          <!-- Mouvements de stock -->
          <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Mouvements de Stock</h2>
            <div v-if="contenu.mouvements && contenu.mouvements.length > 0" class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="mouvement in contenu.mouvements" :key="mouvement.id">
                    <td class="px-6 py-4 text-sm text-gray-900">{{ mouvement.produit?.nom }}</td>
                    <td class="px-6 py-4 text-sm">
                      <span :class="mouvement.type === 'entree' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 rounded text-xs">
                        {{ mouvement.type === 'entree' ? 'Entrée' : 'Sortie' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ mouvement.quantite }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formatCurrency(mouvement.prix_total) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-gray-500 text-center py-8">
              Aucun mouvement de stock
            </div>
          </div>

          <!-- Journaux -->
          <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Opérations Journaux</h2>
            <div v-if="contenu.journaux && contenu.journaux.length > 0" class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heure</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="journal in contenu.journaux" :key="journal.id">
                    <td class="px-6 py-4 text-sm text-gray-900">{{ formatTime(journal.dateHeure_operation) }}</td>
                    <td class="px-6 py-4 text-sm">
                      <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                        {{ journal.type }}
                      </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ journal.description || '-' }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formatCurrency(journal.montant) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-gray-500 text-center py-8">
              Aucune opération de journal
            </div>
          </div>
        </div>

        <!-- Contenu hebdomadaire/mensuel -->
        <div v-else class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-semibold mb-4">Détails par Jour</h2>
          <div v-if="contenu.mouvements_par_jour" class="space-y-4">
            <div 
              v-for="(data, date) in contenu.mouvements_par_jour"
              :key="date"
              class="border border-gray-200 rounded p-4"
            >
              <h3 class="font-semibold text-gray-900 mb-2">{{ formatDate(date) }}</h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <p class="text-gray-600">Entrées</p>
                  <p class="text-lg font-bold text-green-600">{{ data.entrees }}</p>
                </div>
                <div>
                  <p class="text-gray-600">Sorties</p>
                  <p class="text-lg font-bold text-red-600">{{ data.sorties }}</p>
                </div>
                <div>
                  <p class="text-gray-600">Montant</p>
                  <p class="text-lg font-bold text-gray-900">{{ formatCurrency(data.montant) }}</p>
                </div>
                <div v-if="contenu.journaux_par_jour && contenu.journaux_par_jour[date]">
                  <p class="text-gray-600">Journal</p>
                  <p class="text-lg font-bold text-gray-900">{{ formatCurrency(contenu.journaux_par_jour[date].montant) }}</p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-gray-500 text-center py-8">
            Aucune donnée disponible
          </div>
        </div>

        <!-- Bouton télécharger -->
        <div class="mt-8 text-center">
          <a 
            href="/rapport"
            download
            class="inline-block px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
          >
            Télécharger le Rapport
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

const _props = defineProps({
  rapport: Object,
  contenu: Object,
  resume: Object,
})

const capitalizeType = (type) => {
  const types = {
    'journalier': 'Journalier',
    'hebdomadaire': 'Hebdomadaire',
    'mensuel': 'Mensuel'
  }
  return types[type] || type
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('fr-FR')
}

const formatTime = (dateTime) => {
  const date = new Date(dateTime)
  return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const formatCurrency = (value) => {
  if (!value) return '0.00 €'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(value)
}

const formatKey = (key) => {
  const keys = {
    'total_entrees': 'Total Entrées',
    'total_sorties': 'Total Sorties',
    'montant_mouvements': 'Montant Mouvements',
    'montant_journaux': 'Montant Journaux',
    'nombre_mouvements': 'Mouvements',
    'nombre_operations': 'Opérations',
    'nombre_jours_actifs': 'Jours Actifs',
    'entrees_total': 'Entrées Total',
    'sorties_total': 'Sorties Total',
    'total_montant_mouvements': 'Total Mouvements',
    'total_montant_journaux': 'Total Journaux',
    'total_mouvements': 'Mouvements',
    'total_journaux': 'Journaux'
  }
  return keys[key] || key
}

const formatValue = (key, value) => {
  if (key.includes('montant') || key.includes('total_montant')) {
    return formatCurrency(value)
  }
  return value
}
</script>
