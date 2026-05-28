<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Icon from '@/components/Icon.vue'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useLocalDB } from '@/composables/useLocalDB'
import FeatureGate from '@/components/FeatureGate.vue'
defineOptions({ layout: AppDashboardLayout })

const offlineStore = useOfflineStore()
const localDB = useLocalDB()
const localTransferts = ref<any[]>([])

const displayTransferts = computed<any[]>(() =>
    offlineStore.isOnline
        ? (props.transferts as any[])
        : (localTransferts.value.length > 0 ? localTransferts.value : (props.transferts as any[]))
)

async function loadLocalTransferts() {
    localTransferts.value = await localDB.getTransferts()
}

onMounted(async () => {
    if (!offlineStore.isOnline) await loadLocalTransferts()
    window.addEventListener('primegest:sync-pulled', loadLocalTransferts)
    window.addEventListener('primegest:offline', loadLocalTransferts)
    window.addEventListener('primegest:local-write', loadLocalTransferts)
})

onUnmounted(() => {
    window.removeEventListener('primegest:sync-pulled', loadLocalTransferts)
    window.removeEventListener('primegest:offline', loadLocalTransferts)
    window.removeEventListener('primegest:local-write', loadLocalTransferts)
})

interface Succursale {
  id: number
  nom: string
}

interface Produit {
  id: number
  nom: string
}

interface Transfert {
  id: number
  type: string
  montant?: number | null
  quantite?: number | null
  date_operation?: string | null
  status?: string
  from_label?: string
  to_label?: string
  payload?: Record<string, any> | null
  fromSuccursale?: { nom?: string; manager_user_id?: number }
  toSuccursale?: { nom?: string; manager_user_id?: number }
  produit?: { nom?: string }
  user?: { name?: string }
}

const props = defineProps<{
  transferts: Transfert[]
  succursales: Succursale[]
  produits: Produit[]
  succursaleActive?: number | null
}>()

const page = usePage()

// ✅ FIX 1 — Lecture correcte des props Inertia (page.props est un objet réactif direct, pas une ref)
const currentUser = computed<any>(() => (page.props as any).auth?.user ?? null)
const isSuperAdmin = computed(() => !!(currentUser.value?.is_super_admin))

// ✅ FIX 2 — succursaleActive sans passer par pageProps intermédiaire
const succursaleActive = computed(() =>
  props.succursaleActive ?? (page.props as any)?.succursale_id ?? null
)

const activeTab = ref<'caisse' | 'stock'>('caisse')
const formCaisse = ref({
  to_succursale_id: '',
  montant: '',
  date_operation: new Date().toISOString().slice(0, 16),
})

const formStock = ref({
  to_succursale_id: '',
  produit_id: '',
  quantite: '',
  date_operation: new Date().toISOString().slice(0, 16),
})

const errorMsg = ref('')
const successMsg = ref('')

// Modal mot de passe pour validation/rejet transfert
const passwordModal = ref<{ open: boolean; password: string; action: 'approve' | 'reject'; transfert: Transfert | null; reason: string }>({
  open: false,
  password: '',
  action: 'approve',
  transfert: null,
  reason: '',
})

function openPasswordModal(action: 'approve' | 'reject', t: Transfert) {
  passwordModal.value = { open: true, password: '', action, transfert: t, reason: '' }
}

async function confirmPasswordModal() {
  const { action, transfert, password, reason } = passwordModal.value
  if (!transfert) return
  passwordModal.value.open = false
  if (action === 'approve') {
    await router.post(`/transferts/${transfert.id}/approve`, { admin_password: password }, { preserveScroll: true })
  } else {
    await router.post(`/transferts/${transfert.id}/reject`, { admin_password: password, reason }, { preserveScroll: true })
  }
}

const hasSuccursales = computed(() => (props.succursales || []).length > 0)
const canTransfer = computed(() => hasSuccursales.value)

const filteredSuccursales = computed(() => {
  const base = props.succursales || []
  if (succursaleActive.value) {
    return base.filter(s => s.id !== succursaleActive.value)
  }
  return base
})

function goDashboard() {
  router.get('/dashboard')
}

onMounted(() => {
  console.log("Données de la page (props) :", page.props);
  console.log("Utilisateur actuel (auth) :", (page.props as any).auth?.user);
  const params = new URLSearchParams(window.location.search)
  const tab = params.get('tab')
  if (tab === 'stock') {
    activeTab.value = 'stock'
  } else if (tab === 'caisse') {
    activeTab.value = 'caisse'
  }
})

async function submitCaisse() {
  errorMsg.value = ''
  successMsg.value = ''
  await router.post('/transferts/caisse', {
    to_succursale_id: formCaisse.value.to_succursale_id,
    montant: formCaisse.value.montant,
    date_operation: formCaisse.value.date_operation,
  }, {
    onError: (errs) => {
      errorMsg.value = errs?.admin_password || errs?.montant || errs?.to_succursale_id || 'Erreur lors du transfert.'
    },
    onSuccess: () => {
      successMsg.value = 'Demande de transfert de caisse enregistrée.'
      formCaisse.value.montant = ''
    },
    preserveScroll: true,
  })
}

async function submitStock() {
  errorMsg.value = ''
  successMsg.value = ''
  await router.post('/transferts/stock', {
    to_succursale_id: formStock.value.to_succursale_id,
    produit_id: formStock.value.produit_id,
    quantite: formStock.value.quantite,
    date_operation: formStock.value.date_operation,
  }, {
    onError: (errs) => {
      errorMsg.value = errs?.admin_password || errs?.quantite || errs?.produit_id || errs?.to_succursale_id || 'Erreur lors du transfert.'
    },
    onSuccess: () => {
      successMsg.value = 'Demande de transfert de stock enregistrée.'
      formStock.value.quantite = ''
    },
    preserveScroll: true,
  })
}

function formatDateTime(value?: string | null): string {
  if (!value) return '-'
  try {
    const d = new Date(value)
    if (!isNaN(d.getTime())) {
      return new Intl.DateTimeFormat('fr-FR', {
        timeZone: 'Africa/Lubumbashi',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      }).format(d)
    }
  } catch (_e) {}
  return String(value).replace('T', ' ').substring(0, 16)
}

function displayFrom(t: Transfert): string {
  return t.from_label || t.fromSuccursale?.nom || t.payload?.from_succursale || 'Central'
}

function displayTo(t: Transfert): string {
  return t.to_label || t.toSuccursale?.nom || t.payload?.to_succursale || 'Central'
}

function displayProductOrAmount(t: Transfert): string {
  if (t.type === 'stock') {
    const produit = t.produit?.nom || t.payload?.produit || '-'
    const quantite = t.quantite || t.payload?.quantite || 0
    return `${produit} (${quantite})`
  }
  return String(t.montant || t.payload?.montant || 0)
}

// ✅ FIX 3 — canValidate fiable maintenant que currentUser est bien lu
function canValidate(t: Transfert): boolean {
  if (isSuperAdmin.value) return true
  const managerId = t.fromSuccursale?.manager_user_id
  return !!managerId && Number(currentUser.value?.id) === Number(managerId)
}

function approveTransfer(t: Transfert) {
  openPasswordModal('approve', t)
}

function rejectTransfer(t: Transfert) {
  openPasswordModal('reject', t)
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold flex items-center gap-2">
        <Icon name="swap_horiz" class="text-blue-600" />
        Transferts inter succursales
      </h1>
      <button type="button" class="bg-gray-600 text-white px-4 py-2 rounded" @click="goDashboard">Dashboard</button>
    </div>

    <FeatureGate feature="succursales">
      <div v-if="!hasSuccursales" class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded mb-6">
        Ajoutez au moins une succursale pour activer les transferts.
      </div>
      <div v-else-if="!succursaleActive" class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded mb-6">
        Mode centralisé : vous pouvez transférer vers une succursale.
      </div>

      <div class="flex items-center gap-3 mb-4">
        <button
          type="button"
          class="px-4 py-2 rounded"
          :class="activeTab === 'caisse' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
          @click="activeTab = 'caisse'"
        >
          Caisse
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded"
          :class="activeTab === 'stock' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
          @click="activeTab = 'stock'"
        >
          Stock
        </button>
      </div>

      <div v-if="activeTab === 'caisse'" class="bg-white shadow rounded p-4 mb-8">
        <h2 class="font-semibold mb-3">Transfert de caisse</h2>
        <form @submit.prevent="submitCaisse" class="space-y-3">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Succursale de destination</label>
            <select v-model="formCaisse.to_succursale_id" class="w-full border rounded px-3 py-2" :disabled="!canTransfer">
              <option value="" disabled>Choisir...</option>
              <option v-for="s in filteredSuccursales" :key="s.id" :value="s.id">{{ s.nom }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Montant</label>
            <input v-model="formCaisse.montant" type="number" min="0" step="0.01" class="w-full border rounded px-3 py-2" :disabled="!canTransfer" />
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Date et heure</label>
            <input v-model="formCaisse.date_operation" type="datetime-local" class="w-full border rounded px-3 py-2" :disabled="!canTransfer" />
          </div>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded" :disabled="!canTransfer">Transférer</button>
        </form>
      </div>

      <div v-if="activeTab === 'stock'" class="bg-white shadow rounded p-4 mb-8">
        <h2 class="font-semibold mb-3">Transfert de stock</h2>
        <form @submit.prevent="submitStock" class="space-y-3">
          <div>
            <label class="block text-sm text-gray-700 mb-1">Succursale de destination</label>
            <select v-model="formStock.to_succursale_id" class="w-full border rounded px-3 py-2" :disabled="!canTransfer">
              <option value="" disabled>Choisir...</option>
              <option v-for="s in filteredSuccursales" :key="s.id" :value="s.id">{{ s.nom }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Produit</label>
            <select v-model="formStock.produit_id" class="w-full border rounded px-3 py-2" :disabled="!canTransfer">
              <option value="" disabled>Choisir...</option>
              <option v-for="p in props.produits" :key="p.id" :value="p.id">{{ p.nom }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Quantité</label>
            <input v-model="formStock.quantite" type="number" min="0" step="0.01" class="w-full border rounded px-3 py-2" :disabled="!canTransfer" />
          </div>
          <div>
            <label class="block text-sm text-gray-700 mb-1">Date et heure</label>
            <input v-model="formStock.date_operation" type="datetime-local" class="w-full border rounded px-3 py-2" :disabled="!canTransfer" />
          </div>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded" :disabled="!canTransfer">Transférer</button>
        </form>
      </div>
    </FeatureGate>

    <div v-if="errorMsg" class="text-red-600 mb-4">{{ errorMsg }}</div>
    <div v-if="successMsg" class="text-green-600 mb-4">{{ successMsg }}</div>

    <div class="bg-white shadow rounded">
     <div class="overflow-x-auto -mx-4 sm:mx-0">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left">Date</th>
            <th class="px-4 py-2 text-left">Type</th>
            <th class="px-4 py-2 text-left">De</th>
            <th class="px-4 py-2 text-left">Vers</th>
            <th class="px-4 py-2 text-left">Produit / Montant</th>
            <th class="px-4 py-2 text-left">Utilisateur</th>
            <th class="px-4 py-2 text-left">Statut</th>
            <th class="px-4 py-2 text-left">Action</th>
          </tr>
        </thead>
        <tbody v-if="displayTransferts.length">
          <tr v-for="t in displayTransferts" :key="t.id" class="border-t">
            <td class="px-4 py-2">{{ formatDateTime(t.date_operation || '') }}</td>
            <td class="px-4 py-2">{{ t.type === 'stock' ? 'Stock' : 'Caisse' }}</td>
            <td class="px-4 py-2">{{ displayFrom(t) }}</td>
            <td class="px-4 py-2">{{ displayTo(t) }}</td>
            <td class="px-4 py-2">{{ displayProductOrAmount(t) }}</td>
            <td class="px-4 py-2">{{ t.user?.name || '-' }}</td>
            <td class="px-4 py-2">
              {{ t.status === 'validated' ? 'Validé' : t.status === 'rejected' ? 'Rejeté' : t.status === 'pending' ? 'En attente' : (t.status || '-') }}
            </td>
            <td class="px-4 py-2">
              <div v-if="t.status === 'pending' && canValidate(t)" class="flex items-center gap-2">
                <FeatureGate feature="succursales" mode="inline">
                  <button class="text-green-600 hover:underline" type="button" @click="approveTransfer(t)">Valider</button>
                </FeatureGate>
                <FeatureGate feature="succursales" mode="inline">
                  <button class="text-red-600 hover:underline" type="button" @click="rejectTransfer(t)">Rejeter</button>
                </FeatureGate>
              </div>
              <span v-else class="text-gray-400">-</span>
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr>
            <td colspan="8" class="text-center py-6 text-gray-400">Aucun transfert enregistré.</td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>

    <!-- Modal confirmation mot de passe transfert -->
    <div v-if="passwordModal.open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg w-full max-w-sm shadow-xl">
        <h2 class="text-lg font-bold mb-4">
          {{ passwordModal.action === 'approve' ? 'Valider le transfert' : 'Rejeter le transfert' }}
        </h2>
        <div v-if="passwordModal.action === 'reject'" class="mb-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Motif du rejet (optionnel)</label>
          <input v-model="passwordModal.reason" type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="Motif..." />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe Super Admin / Manager</label>
          <input v-model="passwordModal.password" type="password" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" placeholder="••••••••" autofocus />
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" @click="passwordModal.open = false" class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
            Annuler
          </button>
          <button
            type="button"
            @click="confirmPasswordModal"
            :disabled="!passwordModal.password"
            :class="passwordModal.action === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            class="px-4 py-2 text-sm text-white rounded disabled:opacity-50"
          >
            {{ passwordModal.action === 'approve' ? 'Valider' : 'Rejeter' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
