<template>
  <div class="min-h-screen bg-gray-100 p-6 relative overflow-hidden">

    <!-- Arrière-plan graphique -->
    <div class="absolute inset-0 z-0">
      <svg class="absolute top-5 left-10 w-36 h-36 text-blue-200 opacity-30 animate-spin-slow" fill="none" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="45" stroke="currentColor" stroke-width="10"/>
      </svg>
    </div>

    <!-- Titre -->
    <h1 class="text-3xl font-bold mb-6 text-gray-700">Mes entreprises</h1>

    <!-- Liste des entreprises -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="entreprise in entreprises" :key="entreprise.id"
           class="bg-white p-6 rounded-3xl shadow hover:shadow-lg transition relative">

        <!-- Nom et email -->
        <h2 class="text-xl font-bold text-gray-800">{{ entreprise.name }}</h2>
        <p class="text-gray-500 mb-2">{{ entreprise.email }}</p>

        <!-- Téléphone et address -->
        <p class="text-gray-500 text-sm mb-2">📞 {{ entreprise.phone || 'Non défini' }}</p>
        <p class="text-gray-500 text-sm mb-4">🏠 {{ entreprise.address || 'Non défini' }}</p>

        <!-- Mini graphique (SVG) -->
        <svg class="w-full h-12" viewBox="0 0 100 40">
          <polyline :points="generateGraphPoints(entreprise)" fill="none" stroke="#3B82F6" stroke-width="3"/>
        </svg>

        <!-- Bouton action -->
        <div class="mt-4 flex justify-end">
          <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-full transition"
                  @click="viewEntreprise(entreprise)">
            Voir
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

// données reçues du contrôleur
const props = defineProps({
  entreprises: Array
})

// fonction pour générer un mini-graph SVG
const generateGraphPoints = (entreprise) => {
  // Ici on fait juste un exemple aléatoire pour le visuel
  const points = []
  for (let i = 0; i < 10; i++) {
    const x = i * 10
    const y = 40 - Math.floor(Math.random() * 40)
    points.push(`${x},${y}`)
  }
  return points.join(' ')
}

// fonction pour voir une entreprise
const viewEntreprise = (entreprise) => {
  // Aucune route entreprise.show n'existe: fallback vers dashboard.
  router.get('/dashboard')
}
</script>

<style>
@keyframes spin-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.animate-spin-slow {
  animation: spin-slow 30s linear infinite;
}
</style>
