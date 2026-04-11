<template>
  <div class="p-6 space-y-6">
    <!-- HEADER -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Archives des journaux</h1>
      <div class="flex gap-2">
        <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Retour
        </button>
      </div>
    </div>

    <!-- LISTE DES ARCHIVES -->
    <div v-if="archiveDates.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="archive in archiveDates" :key="archive.date" class="bg-white p-6 rounded shadow hover:shadow-lg transition">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="text-lg font-semibold">{{ archive.date_formatted }}</h3>
            <p class="text-gray-600 text-sm">{{ archive.jour_semaine }}</p>
          </div>
          <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded text-sm font-semibold">
            {{ archive.count }} journal{{ archive.count > 1 ? 'aux' : '' }}
          </div>
        </div>

        <div class="flex gap-2 pt-4 border-t">
          <button 
            @click="previewArchive(archive.date)"
            class="flex-1 px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition flex items-center justify-center gap-1"
          >
            <Icon name="preview" class="text-base" />
            Aperçu
          </button>
          <button 
            @click="downloadArchive(archive.date)"
            class="flex-1 px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition flex items-center justify-center gap-1"
          >
            <Icon name="download" class="text-base" />
            Télécharger
          </button>
          <button 
            @click="printArchive(archive.date)"
            class="flex-1 px-3 py-2 bg-purple-600 text-white rounded text-sm hover:bg-purple-700 transition flex items-center justify-center gap-1"
          >
            <Icon name="print" class="text-base" />
            Imprimer
          </button>
        </div>
      </div>
    </div>

    <!-- AUCUNE ARCHIVE -->
    <div v-else class="bg-white p-6 rounded shadow text-center text-gray-400">
      <Icon name="archive" class="text-6xl block mb-2" />
      Aucune archive de journal trouvée
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import Icon from '@/components/Icon.vue'

const props = defineProps({
  archiveDates: Array
})

function goBack() {
  router.get('/journals')
}

function previewArchive(date) {
  router.get(`/archives/journaux/${date}/preview`)
}

function downloadArchive(date) {
  window.location.href = `/archives/journaux/${date}/download`
}

function printArchive(date) {
  window.location.href = `/archives/journaux/${date}/preview?print=true`
}
</script>
