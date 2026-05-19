<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">Archivage – {{ monthName }} {{ year }}</h1>
      <div class="flex gap-2">
        <button @click="goBack" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Retour
        </button>
      </div>
    </div>

    <div class="bg-white p-4 rounded shadow">
      <div class="grid grid-cols-7 gap-2 text-center text-sm font-semibold text-gray-600 mb-2">
        <div>L</div><div>M</div><div>M</div><div>J</div><div>V</div><div>S</div><div>D</div>
      </div>

      <div class="grid grid-cols-7 gap-2">
        <div v-for="(cell, idx) in calendarCells" :key="idx">
          <button
            v-if="cell.day"
            @click="openDay(cell.date)"
            class="w-full h-12 rounded border text-sm"
            :class="cell.hasArchive ? 'bg-white border-blue-300 hover:border-blue-500' : 'bg-gray-50 border-gray-200 text-gray-400'"
          >
            {{ cell.day }}
          </button>
          <div v-else class="w-full h-12"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const props = defineProps({
  type: String,
  year: Number,
  month: Number,
  days: Object,
})

const monthNames = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
]

const monthName = monthNames[(props.month || 1) - 1]

function goBack() {
  router.get('/archives', { type: props.type, year: props.year })
}

function openDay(date) {
  router.get(`/archives/${props.type}/${date}`)
}

function buildCalendar() {
  const y = props.year
  const m = props.month - 1
  const first = new Date(y, m, 1)
  const last = new Date(y, m + 1, 0)
  const startDay = (first.getDay() + 6) % 7 // Lundi = 0
  const totalDays = last.getDate()

  const cells = []
  for (let i = 0; i < startDay; i++) cells.push({ day: null })
  for (let d = 1; d <= totalDays; d++) {
    const date = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    cells.push({
      day: d,
      date,
      hasArchive: !!props.days?.[date],
    })
  }
  return cells
}

const calendarCells = buildCalendar()
</script>
