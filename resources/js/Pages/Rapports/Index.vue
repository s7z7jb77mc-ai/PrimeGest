<template>
  <div class="min-h-screen bg-gray-50">
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Rapports</h1>
            <p class="mt-2 text-gray-600">Consultez et générez des rapports détaillés</p>
          </div>
          <button 
            @click="showGenerateModal = true"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
          >
            Générer un Rapport
          </button>
        </div>

        <!-- Onglets des types de rapports -->
        <div class="bg-white rounded-lg shadow mb-8">
          <div class="flex border-b border-gray-200">
            <button 
              v-for="type in ['journalier', 'hebdomadaire', 'mensuel']"
              :key="type"
              @click="selectType(type)"
              :class="[
                'flex-1 px-4 py-4 text-center font-medium transition',
                type_actif === type 
                  ? 'text-blue-600 border-b-2 border-blue-600' 
                  : 'text-gray-600 hover:text-gray-900'
              ]"
            >
              {{ capitalizeType(type) }}
            </button>
          </div>
        </div>

        <!-- Liste des rapports -->
        <div class="grid gap-6">
          <div 
            v-for="rapport in rapports.data" 
            :key="rapport.id"
            class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition cursor-pointer"
            @click="goToRapport(rapport.id)"
          >
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">
                  Rapport {{ capitalizeType(rapport.type) }}
                </h3>
                <p class="mt-2 text-sm text-gray-600">
                  <span v-if="rapport.type === 'journalier'">
                    {{ formatDate(rapport.date_debut) }}
                  </span>
                  <span v-else>
                    Du {{ formatDate(rapport.date_debut) }} au {{ formatDate(rapport.date_fin) }}
                  </span>
                </p>
              </div>
              <div class="text-right">
                <p class="text-sm text-gray-500 mb-2">Généré le {{ formatDateTime(rapport.date_generation) }}</p>
                <div class="flex gap-2">
                  <a 
                    :href="`/rapport?type=${rapport.type}&date=${rapport.date_debut}`"
                    class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition"
                  >
                    Télécharger
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div v-if="rapports.data.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
            <p class="text-gray-500">Aucun rapport disponible pour cette période</p>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="rapports.data.length > 0" class="bg-white rounded-lg shadow mt-8 px-6 py-4 flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Affichage {{ rapports.from }} à {{ rapports.to }} sur {{ rapports.total }} résultats
          </div>
          <div class="flex gap-2">
            <Link 
              v-if="rapports.prev_page_url"
              :href="rapports.prev_page_url"
              class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Précédent
            </Link>
            <Link 
              v-if="rapports.next_page_url"
              :href="rapports.next_page_url"
              class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Suivant
            </Link>
          </div>
        </div>

        <!-- Modal de génération -->
        <div v-if="showGenerateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h2 class="text-2xl font-bold mb-4">Générer un Rapport</h2>
            
            <form @submit.prevent="generateRapport" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type de Rapport</label>
                <select 
                  v-model="generateForm.type"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500"
                >
                  <option value="journalier">Journalier</option>
                  <option value="hebdomadaire">Hebdomadaire</option>
                  <option value="mensuel">Mensuel</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input 
                  v-model="generateForm.date"
                  type="date"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500"
                  required
                />
              </div>

              <div class="flex gap-2 pt-4">
                <button 
                  type="button"
                  @click="showGenerateModal = false"
                  class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition"
                >
                  Annuler
                </button>
                <button 
                  type="submit"
                  class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
                  :disabled="isGenerating"
                >
                  {{ isGenerating ? 'Génération...' : 'Générer' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const _props = defineProps({
  rapports: Object,
  type_actif: String,
})

const showGenerateModal = ref(false)
const isGenerating = ref(false)
const generateForm = ref({
  type: 'journalier',
  date: new Date().toISOString().split('T')[0],
})

const selectType = (type) => {
  router.get('/rapport', { type })
}

const goToRapport = (id) => {
  router.visit('/rapport')
}

const generateRapport = async () => {
  isGenerating.value = true
  try {
    router.get('/rapport', generateForm.value, {
      onSuccess: () => {
        showGenerateModal.value = false
        isGenerating.value = false
      },
      onError: () => {
        isGenerating.value = false
      }
    })
  } catch (error) {
    isGenerating.value = false
    console.error('Erreur lors de la génération du rapport:', error)
  }
}

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

const formatDateTime = (dateTime) => {
  const date = new Date(dateTime)
  return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}
</script>
