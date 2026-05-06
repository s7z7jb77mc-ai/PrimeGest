<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useOfflineQueue } from '@/composables/useOfflineQueue'
import { useLocalDB } from '@/composables/useLocalDB'
defineOptions({ layout: AppDashboardLayout })

// Props envoyés depuis le controller
const props = defineProps({
  journals: { type: Array, default: () => [] }
})

// Modal
const modalOpen = ref(false)
const t = _t
const lang = useLang()

const offlineStore = useOfflineStore()
const { queueOperation } = useOfflineQueue()
const localDB = useLocalDB()
const localJournals = ref<any[]>([])

async function loadLocalJournals() {
    if (localDB.isAvailable) {
        localJournals.value = await localDB.getJournals()
    }
}

onMounted(async () => {
    if (!offlineStore.isOnline) await loadLocalJournals()
    window.addEventListener('primegest:sync-pulled', loadLocalJournals)
})

const displayJournals = computed<any[]>(() =>
    offlineStore.isOnline ? (props.journals as any[]) : localJournals.value
)

// Date format pour datetime-local
function nowForDatetimeLocal() {
  const d = new Date()
  const fmt = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Africa/Lubumbashi',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
  const value = fmt.format(d).replace(' ', 'T')
  return value
}

// Formulaire
const form = useForm({
  dateHeure_operation: nowForDatetimeLocal(),
  type: '',
  description: '',
  montant: ''
})

// Navigation
function goDashboard() {
  router.get('/dashboard')
}

// Modal
function openModal() {
  form.reset()
  form.dateHeure_operation = nowForDatetimeLocal()
  modalOpen.value = true
}
function closeModal() {
  modalOpen.value = false
}

// Format date affichée
function formatDateTimeShort(dateStr) {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    if (!isNaN(d.getTime())) {
      const fmt = new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'Africa/Lubumbashi',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      })
      return fmt.format(d)
    }
  } catch (_e) {}
  let s = String(dateStr).trim()
  s = s.replace('T', ' ')
  if (/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/.test(s)) s += ':00'
  if (s.indexOf('.') !== -1) s = s.split('.')[0]
  return s.substring(0, 19)
}

// Soumission
async function submit() {
  if (!offlineStore.isOnline) {
    await queueOperation('journals', crypto.randomUUID(), 'create', {
      type: form.type,
      description: form.description,
      montant: form.montant,
      dateHeure_operation: form.dateHeure_operation,
    })
    modalOpen.value = false
    form.reset()
    alert('Hors ligne — opération sauvegardée, synchronisation dès reconnexion.')
    return
  }
  form.post('/journals', {
    onSuccess: () => {
      modalOpen.value = false
      form.reset()
    }
  })
}

// Erreurs serveur
const hasErrors = computed(() => Object.keys(form.errors).length > 0)
</script>

<template>
  <div class="p-6 space-y-6" :key="lang">
    <!-- Bannière hors-ligne -->
    <div v-if="!offlineStore.isOnline" class="px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
      Mode hors-ligne — données locales (lecture seule)
    </div>

    <!-- Header -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">{{ t('journal') }}</h1>
      <div class="flex gap-2">
        <button type="button" @click="openModal" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
          Nouvelle opération
        </button>
        <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          {{ t('dashboard') }}
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white shadow rounded p-4">
      <div class="overflow-x-auto">
      <div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Date & heure</th>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-left">Description</th>
              <th class="px-4 py-2 text-right">Montant</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="j in displayJournals" :key="(j as any).id ?? (j as any).uuid" class="border-t">
              <td class="px-4 py-2">{{ formatDateTimeShort((j as any).dateHeure_operation ?? (j as any).date_heure_operation) }}</td>
              <td class="px-4 py-2 capitalize">{{ (j as any).type ?? (j as any).type_journal }}</td>
              <td class="px-4 py-2">{{ (j as any).description || '-' }}</td>
              <td class="px-4 py-2 text-right">{{ Number((j as any).montant).toFixed(2) }}</td>
            </tr>
            <tr v-if="!displayJournals.length">
              <td colspan="4" class="text-center py-6 text-gray-400">
                {{ offlineStore.isOnline ? 'Aucun enregistrement dans le journal' : 'Aucune opération en cache local' }}
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
    <div class="bg-white p-6 rounded-lg w-full max-w-lg">
      <h2 class="text-xl font-bold mb-4">Nouvelle opération</h2>

      <form @submit.prevent="submit" class="grid grid-cols-1 gap-4">
        <!-- Date -->
        <div>
          <label class="block mb-1 font-semibold">Date & heure</label>
          <input
            type="datetime-local"
            v-model="form.dateHeure_operation"
            class="w-full border p-2 rounded"
          />
          <p v-if="form.errors.dateHeure_operation" class="text-red-600 text-sm mt-1">{{ form.errors.dateHeure_operation }}</p>
        </div>

        <!-- Type -->
        <div>
          <label class="block mb-1 font-semibold">Type</label>
          <select v-model="form.type" class="w-full border p-2 rounded">
            <option value="" disabled>Choisir le type</option>
            <option value="entree">Entrée(revenue, produit)</option>
            <option value="sortie">Sortie(depense, charge)</option>
          </select>
          <p v-if="form.errors.type" class="text-red-600 text-sm mt-1">{{ form.errors.type }}</p>
        </div>

        <!-- Description -->
        <div>
          <label class="block mb-1 font-semibold">Description</label>
          <input type="text" v-model="form.description" class="w-full border p-2 rounded" />
          <p v-if="form.errors.description" class="text-red-600 text-sm mt-1">{{ form.errors.description }}</p>
        </div>

        <!-- Montant -->
        <div>
          <label class="block mb-1 font-semibold">Montant</label>
          <input type="number" step="0.01" v-model="form.montant" class="w-full border p-2 rounded text-right" />
          <p v-if="form.errors.montant" class="text-red-600 text-sm mt-1">{{ form.errors.montant }}</p>
        </div>

        <!-- Server errors -->
        <div v-if="hasErrors" class="bg-red-50 border border-red-200 p-2 rounded text-sm text-red-700">
          Vérifie les champs en rouge ou le message serveur.
          <div v-if="form.errors.server" class="mt-1">{{ form.errors.server }}</div>
          <div v-else-if="Object.keys(form.errors).length" class="mt-1">{{ Object.values(form.errors)[0] }}</div>
        </div>

        <div class="flex justify-end gap-2 mt-2">
          <button type="button" @click="closeModal" class="bg-gray-400 text-white px-4 py-2 rounded">Annuler</button>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</template>
