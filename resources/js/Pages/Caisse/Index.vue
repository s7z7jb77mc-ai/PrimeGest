<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { router, usePage, useForm } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useLocalDB } from '@/composables/useLocalDB'
import { useOfflineQueue } from '@/composables/useOfflineQueue'
defineOptions({ layout: AppDashboardLayout })

const offlineStore = useOfflineStore()
const localDB = useLocalDB()
const localCaisses = ref<any[]>([])

async function loadLocalCaisses() {
    localCaisses.value = await localDB.getCaisses()
}

onMounted(async () => {
    if (!offlineStore.isOnline) await loadLocalCaisses()
    window.addEventListener('primegest:sync-pulled', loadLocalCaisses)
    window.addEventListener('primegest:offline', loadLocalCaisses)
    window.addEventListener('primegest:local-write', loadLocalCaisses)
})

onUnmounted(() => {
    window.removeEventListener('primegest:sync-pulled', loadLocalCaisses)
    window.removeEventListener('primegest:offline', loadLocalCaisses)
    window.removeEventListener('primegest:local-write', loadLocalCaisses)
})

interface CaisseItem {
  id: number
  date_operation: string | null
  created_at?: string | null
  description: string
  entree: number
  sortie: number
  solde: number
  type_operation?: string | null
  solde_cumule?: number
}

// Props
const props = defineProps<{
  caisses: CaisseItem[]
  devise?: string
  hasInitial?: boolean
  caisseInitiale?: CaisseItem | null
  succursale_id?: number | null
}>()

const displayCaisses = computed<CaisseItem[]>(() =>
    offlineStore.isOnline
        ? props.caisses
        : (localCaisses.value.length > 0 ? (localCaisses.value as CaisseItem[]) : props.caisses)
)

// Calculs des totaux
const totalEntree = computed(() =>
  displayCaisses.value.reduce((sum: number, c: CaisseItem) => sum + (Number(c.entree) || 0), 0)
)

const totalSortie = computed(() =>
  displayCaisses.value.reduce((sum: number, c: CaisseItem) => sum + (Number(c.sortie) || 0), 0)
)

const soldeTotal = computed(() => totalEntree.value - totalSortie.value)
const t = _t
const lang = useLang()
const page = usePage()
const pageProps = computed(() => page.props?.value ?? page.props ?? {})
const multiSuccursales = computed(() => !!pageProps.value.parametres?.multi_succursales || !!pageProps.value.has_succursales)
const isDecentralized = computed(() => !!props.succursale_id || !!pageProps.value?.succursale_id || !!pageProps.value?.succursale_name)

const initialForm = useForm({
  montant: '' as string | number,
  description: 'Solde initial (manuel)',
  date_operation: new Date().toISOString().split('T')[0],
})

const errorMsg  = ref('')
const successMsg = ref('')

// Formater les montants avec la devise du prop
function formatCurrency(value: number | undefined): string {
  const devise = props.devise || 'XOF'
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: devise,
    minimumFractionDigits: 0,
  }).format(value || 0)
}

async function enregistrerSoldeInitial(): Promise<void> {
  if (props.hasInitial) return
  if (!offlineStore.isOnline) {
    const { queueOperation } = useOfflineQueue()
    await queueOperation('caisses', crypto.randomUUID(), 'create', {
      description: initialForm.description,
      date_operation: initialForm.date_operation || new Date().toISOString().split('T')[0],
      entree: Number(initialForm.montant),
      sortie: 0,
      type_operation: 'initial',
    })
    successMsg.value = 'Sauvegardé localement — sync dès la reconnexion.'
    return
  }

  errorMsg.value  = ''
  successMsg.value = ''

  initialForm.transform(data => ({
    ...data,
    montant: Number(data.montant),
    date_operation: data.date_operation || null,
  })).post('/caisse/initial', {
    preserveScroll: true,
    onSuccess: () => {
      successMsg.value = 'Solde initial enregistré.'
    },
    onError: (errors) => {
      errorMsg.value = errors.montant || errors.message || 'Impossible d\'enregistrer le solde initial.'
    },
  })
}

// Aller au dashboard
function goDashboard(): void {
  router.get('/dashboard')
}

// Calculer le solde cumulé pour chaque ligne
function getSoldeAtIndex(index: number): number {
  return Number(displayCaisses.value[index]?.solde_cumule || 0)
}

function formatDateTime(value?: string | null): string {
  if (!value) return '-'
  try {
    const d = new Date(value)
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
  return String(value).replace('T', ' ').substring(0, 19)
}
</script>

<template>
  <div class="p-4 sm:p-6" :key="lang">
    <!-- Bannière hors-ligne -->
    <div v-if="!offlineStore.isOnline" class="mb-4 px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
      Mode hors-ligne — modifications sauvegardées localement, sync dès la reconnexion
    </div>

    <!-- Header -->
    <div class="flex flex-wrap justify-between gap-3 mb-4">
      <h1 class="text-2xl font-bold">{{ t('cash_title') }}</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <button
          v-if="multiSuccursales"
          type="button"
          @click="router.get('/transferts', { tab: 'caisse' })"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
        >
          Transfert
        </button>
        <button
          type="button"
          @click="goDashboard"
          class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"
        >
          {{ t('dashboard') }}
        </button>
      </div>
    </div>

    <!-- Solde initial -->
    <div v-if="!isDecentralized" class="bg-white shadow rounded p-4 mb-6 border border-gray-200">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">Solde initial de la caisse</h2>
        <span v-if="hasInitial" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Défini</span>
        <span v-else class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded">À définir</span>
      </div>

      <div v-if="hasInitial" class="text-sm text-gray-600">
        <p>Solde initial déjà défini. Cette entrée est unique et sert de base au suivi de la caisse.</p>
        <p v-if="caisseInitiale?.date_operation" class="mt-1">
          Date: {{ formatDateTime(caisseInitiale.date_operation) }}
        </p>
        <p class="mt-1 font-semibold text-gray-800">
          Montant: {{ formatCurrency(caisseInitiale?.entree || 0) }}
        </p>
      </div>

      <form v-else class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end" @submit.prevent="enregistrerSoldeInitial">
        <div>
          <label class="block text-sm text-gray-700 mb-1">Montant</label>
          <input
            v-model="initialForm.montant"
            type="number"
            min="0"
            step="0.01"
            class="w-full border border-gray-300 rounded px-3 py-2"
            required
          />
        </div>
        <div>
          <label class="block text-sm text-gray-700 mb-1">Date</label>
          <input
            v-model="initialForm.date_operation"
            type="date"
            class="w-full border border-gray-300 rounded px-3 py-2"
          />
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm text-gray-700 mb-1">Description</label>
          <input
            v-model="initialForm.description"
            type="text"
            class="w-full border border-gray-300 rounded px-3 py-2"
          />
        </div>
        <div class="md:col-span-4">
          <button
            type="submit"
            :disabled="initialForm.processing || !offlineStore.isOnline"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
          >
            {{ offlineStore.isOnline ? 'Enregistrer le solde initial' : 'Connexion requise' }}
          </button>
          <p v-if="errorMsg" class="text-red-600 text-sm mt-2">{{ errorMsg }}</p>
          <p v-if="successMsg" class="text-green-600 text-sm mt-2">{{ successMsg }}</p>
        </div>
      </form>
    </div>

    <!-- Résumé des totaux -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-green-50 p-4 rounded border border-green-200">
        <p class="text-gray-600 text-sm">Total Entrées</p>
        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(totalEntree) }}</p>
      </div>
      <div class="bg-red-50 p-4 rounded border border-red-200">
        <p class="text-gray-600 text-sm">Total Sorties</p>
        <p class="text-2xl font-bold text-red-600">{{ formatCurrency(totalSortie) }}</p>
      </div>
      <div class="bg-blue-50 p-4 rounded border border-blue-200">
        <p class="text-gray-600 text-sm">Solde Total</p>
        <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(soldeTotal) }}</p>
      </div>
    </div>

    <!-- Tableau de la caisse -->
    <div class="overflow-x-auto bg-white shadow rounded">
     <div class="overflow-x-auto -mx-4 sm:mx-0">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left">Date & heure</th>
            <th class="px-4 py-2 text-left">Description</th>
            <th class="px-4 py-2 text-right">Entrée</th>
            <th class="px-4 py-2 text-right">Sortie</th>
            <th class="px-4 py-2 text-right">Solde</th>
          </tr>
        </thead>
        <tbody v-if="displayCaisses.length">
          <tr v-for="(c, index) in displayCaisses" :key="c.id" class="border-t hover:bg-gray-50">
            <td class="px-4 py-2">
              {{ formatDateTime(c.date_operation || c.created_at) }}
            </td>
            <td class="px-4 py-2">
              <span v-if="c.type_operation === 'initial'" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded mr-2">Initial</span>
              {{ c.description || '-' }}
            </td>
            <td class="px-4 py-2 text-right text-green-600 font-semibold">{{ formatCurrency(c.entree) }}</td>
            <td class="px-4 py-2 text-right text-red-600 font-semibold">{{ formatCurrency(c.sortie) }}</td>
            <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(getSoldeAtIndex(index)) }}</td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr>
            <td colspan="5" class="text-center py-6 text-gray-400">
              Aucune opération enregistrée
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </div>
</template>
