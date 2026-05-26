<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineQueue } from '@/composables/useOfflineQueue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useLocalDB } from '@/composables/useLocalDB'
import FeatureGate from '@/components/FeatureGate.vue'
defineOptions({ layout: AppDashboardLayout })

const { queueOperation } = useOfflineQueue()
const offlineStore = useOfflineStore()
const localDB = useLocalDB()

const props = defineProps({
  clients: { type: Array, default: () => [] },
  fournisseurs: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) }
})

const t = _t
const lang = useLang()
const page = usePage()

const localClients = ref<any[]>([])
const localFournisseurs = ref<any[]>([])

async function loadLocalTiers() {
    if (localDB.isAvailable) {
        localClients.value = await localDB.getClients()
        localFournisseurs.value = await localDB.getFournisseurs()
    }
}

onMounted(async () => {
    if (!offlineStore.isOnline) await loadLocalTiers()
    window.addEventListener('primegest:sync-pulled', loadLocalTiers)
    window.addEventListener('primegest:offline', loadLocalTiers)
    window.addEventListener('primegest:local-write', loadLocalTiers)
})

onUnmounted(() => {
    window.removeEventListener('primegest:sync-pulled', loadLocalTiers)
    window.removeEventListener('primegest:offline', loadLocalTiers)
    window.removeEventListener('primegest:local-write', loadLocalTiers)
})

const displayClients = computed<any[]>(() =>
    offlineStore.isOnline
        ? (props.clients as any[])
        : (localClients.value.length > 0 ? localClients.value : (props.clients as any[]))
)
const displayFournisseurs = computed<any[]>(() =>
    offlineStore.isOnline
        ? (props.fournisseurs as any[])
        : (localFournisseurs.value.length > 0 ? localFournisseurs.value : (props.fournisseurs as any[]))
)
const _isSuperAdmin = computed(() => {
  const propsObj = page.props?.value ?? page.props ?? {}
  if (propsObj.auth?.user?.is_super_admin !== undefined) return propsObj.auth.user.is_super_admin === true
  if (propsObj.can_manage !== undefined) return propsObj.can_manage === true
  const role = String(propsObj.auth?.user?.role || '')
  return role.replace(/[\s-]+/g, '_').toLowerCase() === 'super_admin'
})

// Search
const clientSearch = ref(props.filters.client_search || '')
const fournisseurSearch = ref(props.filters.fournisseur_search || '')
function handleClientSearch() {
  router.get('/tiers', { client_search: clientSearch.value, fournisseur_search: fournisseurSearch.value }, { preserveState: true, preserveScroll: true })
}
function handleFournisseurSearch() {
  router.get('/tiers', { client_search: clientSearch.value, fournisseur_search: fournisseurSearch.value }, { preserveState: true, preserveScroll: true })
}

// Client form
const clientModalOpen = ref(false)
const clientDeleteModalOpen = ref(false)
const clientDeleteTargetId = ref(null)
const clientDeletePassword = ref('')
const emptyClient = {
  id: null,
  uuid: null as string | null,
  nom_client: '',
  numero_telephone: '',
  adresse: '',
  return_to: '',
  admin_password: '',
}
const clientForm = useForm({ ...emptyClient })

function openClientModal(client = null) {
  clientForm.clearErrors()
  clientForm.reset()
  if (client) {
    clientForm.id = client.id
    clientForm.uuid = client.uuid ?? null
    clientForm.nom_client = client.nom_client ?? ''
    clientForm.numero_telephone = client.numero_telephone ?? ''
    clientForm.adresse = client.adresse ?? ''
    clientForm.return_to = ''
    clientForm.admin_password = ''
  } else {
    Object.assign(clientForm, { ...emptyClient })
  }
  clientModalOpen.value = true
}

async function submitClientForm() {
  if (!offlineStore.isOnline) {
    const isUpdate = !!clientForm.id
    const recordId = isUpdate ? (clientForm.uuid ?? crypto.randomUUID()) : crypto.randomUUID()
    const operation: 'create' | 'update' = isUpdate ? 'update' : 'create'
    await queueOperation('clients', recordId, operation, { ...clientForm.data() })
    clientModalOpen.value = false
    alert('Hors ligne — opération sauvegardée, elle sera synchronisée dès la reconnexion.')
    return
  }
  if (clientForm.id) {
    if (!clientForm.admin_password) {
      alert('Mot de passe Super Admin requis.')
      return
    }
    clientForm.put(`/clients/${clientForm.id}`, {
      preserveScroll: true,
      onSuccess: () => { clientModalOpen.value = false },
    })
  } else {
    clientForm.post('/clients', {
      preserveScroll: true,
      onSuccess: () => { clientModalOpen.value = false },
    })
  }
}

function deleteClient(id) {
  clientDeleteTargetId.value = id
  clientDeletePassword.value = ''
  clientDeleteModalOpen.value = true
}

function confirmDeleteClient() {
  if (!clientDeleteTargetId.value) return
  if (!clientDeletePassword.value) {
    alert('Mot de passe Super Admin requis.')
    return
  }
  router.delete(`/clients/${clientDeleteTargetId.value}`, { data: { id: clientDeleteTargetId.value, admin_password: clientDeletePassword.value }, preserveScroll: true,
    onSuccess: () => { clientDeleteModalOpen.value = false }
  })
}

function goClientDetail(id) {
  router.get(`/creances-dettes/clients/${id}`)
}

// Fournisseur form
const fournisseurModalOpen = ref(false)
const fournisseurDeleteModalOpen = ref(false)
const fournisseurDeleteTargetId = ref(null)
const fournisseurDeletePassword = ref('')
const emptyFournisseur = {
  id: null,
  uuid: null as string | null,
  nom_entreprise_fournisseur: '',
  adresse: '',
  reduction_pourcentage: 0,
  admin_password: '',
}
const fournisseurForm = useForm({ ...emptyFournisseur })

function openFournisseurModal(fournisseur = null) {
  fournisseurForm.clearErrors()
  fournisseurForm.reset()
  if (fournisseur) {
    fournisseurForm.id = fournisseur.id
    fournisseurForm.uuid = fournisseur.uuid ?? null
    fournisseurForm.nom_entreprise_fournisseur = fournisseur.nom_entreprise_fournisseur ?? ''
    fournisseurForm.adresse = fournisseur.adresse ?? ''
    fournisseurForm.reduction_pourcentage = fournisseur.reduction_pourcentage ?? 0
    fournisseurForm.admin_password = ''
  } else {
    Object.assign(fournisseurForm, { ...emptyFournisseur })
  }
  fournisseurModalOpen.value = true
}

async function submitFournisseurForm() {
  if (!offlineStore.isOnline) {
    const isUpdate = !!fournisseurForm.id
    const recordId = isUpdate ? (fournisseurForm.uuid ?? crypto.randomUUID()) : crypto.randomUUID()
    const operation: 'create' | 'update' = isUpdate ? 'update' : 'create'
    await queueOperation('fournisseurs', recordId, operation, { ...fournisseurForm.data() })
    fournisseurModalOpen.value = false
    alert('Hors ligne — opération sauvegardée, elle sera synchronisée dès la reconnexion.')
    return
  }
  if (fournisseurForm.id) {
    if (!fournisseurForm.admin_password) {
      alert('Mot de passe Super Admin requis.')
      return
    }
    fournisseurForm.put(`/fournisseurs/${fournisseurForm.id}`, {
      preserveScroll: true,
      onSuccess: () => { fournisseurModalOpen.value = false },
    })
  } else {
    fournisseurForm.post('/fournisseurs', {
      preserveScroll: true,
      onSuccess: () => { fournisseurModalOpen.value = false },
    })
  }
}

function deleteFournisseur(id) {
  fournisseurDeleteTargetId.value = id
  fournisseurDeletePassword.value = ''
  fournisseurDeleteModalOpen.value = true
}

function confirmDeleteFournisseur() {
  if (!fournisseurDeleteTargetId.value) return
  if (!fournisseurDeletePassword.value) {
    alert('Mot de passe Super Admin requis.')
    return
  }
  router.delete(`/fournisseurs/${fournisseurDeleteTargetId.value}`, { data: { id: fournisseurDeleteTargetId.value, admin_password: fournisseurDeletePassword.value }, preserveScroll: true,
    onSuccess: () => { fournisseurDeleteModalOpen.value = false }
  })
}

function goFournisseurDetail(id) {
  router.get(`/creances-dettes/fournisseurs/${id}`)
}

function goDashboard() {
  router.get('/dashboard')
}
</script>

<template>
  <div class="p-4 sm:p-6" :key="lang">
    <!-- Bannière hors-ligne -->
    <div v-if="!offlineStore.isOnline" class="mb-4 px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
      Mode hors-ligne — modifications sauvegardées localement, sync dès la reconnexion
    </div>

    <div class="flex flex-wrap justify-between gap-3 mb-4">
      <h1 class="text-2xl font-bold">Tiers</h1>
      <div class="flex gap-2 flex-wrap">
        <button type="button" @click="openClientModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Ajouter un client
        </button>
        <button type="button" @click="openFournisseurModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Ajouter un fournisseur
        </button>
        <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          {{ t('dashboard') }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Clients -->
      <div class="bg-white shadow rounded p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">{{ t('clients') }}</h2>
        </div>

        <div class="mb-4">
          <input
            v-model="clientSearch"
            @keyup.enter="handleClientSearch"
            type="text"
            placeholder="Rechercher par numéro..."
            class="border p-2 rounded w-full"
          />
        </div>

        <div class="overflow-x-auto">
	 <div class="overflow-x-auto -mx-4 sm:mx-0">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Nom</th>
                <th class="px-3 py-2 text-left">Téléphone</th>
                <th class="px-3 py-2 text-left">Adresse</th>
                <th class="px-3 py-2 text-right">Créance</th>
                <th class="px-3 py-2 text-center">Action</th>
              </tr>
            </thead>
            <tbody v-if="displayClients.length">
              <tr v-for="c in displayClients" :key="(c as any).id ?? (c as any).uuid" class="border-t">
                <td class="px-3 py-2">{{ (c as any).nom_client }}</td>
                <td class="px-3 py-2">{{ (c as any).numero_telephone }}</td>
                <td class="px-3 py-2">{{ (c as any).adresse || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ Number((c as any).creance || 0).toFixed(2) }}</td>
                <td class="px-3 py-2 text-center">
                  <template v-if="offlineStore.isOnline">
                    <button type="button" @click="openClientModal(c)" class="text-blue-600 hover:underline">Modifier</button>
                    <button type="button" @click="deleteClient((c as any).id)" class="text-red-600 hover:underline ml-3">Supprimer</button>
                    <FeatureGate feature="dette_tracking" mode="inline">
                      <button type="button" @click="goClientDetail((c as any).id)" class="text-gray-700 hover:underline ml-3">Détail</button>
                    </FeatureGate>
                  </template>
                  <span v-else class="text-gray-400 text-xs">hors-ligne</span>
                </td>
              </tr>
            </tbody>
            <tbody v-else>
              <tr>
                <td colspan="5" class="text-center py-6 text-gray-400">
                  {{ offlineStore.isOnline ? 'Aucun client trouvé' : 'Aucun client en cache local' }}
                </td>
              </tr>
            </tbody>
          </table>
	 </div>
        </div>
      </div>

      <!-- Fournisseurs -->
      <div class="bg-white shadow rounded p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">{{ t('suppliers') }}</h2>
        </div>

        <div class="mb-4">
          <input
            v-model="fournisseurSearch"
            @keyup.enter="handleFournisseurSearch"
            type="text"
            placeholder="Rechercher un fournisseur..."
            class="border p-2 rounded w-full"
          />
        </div>

        <div class="overflow-x-auto">
	 <div class="overflow-x-auto -mx-4 sm:mx-0">
         <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Nom</th>
                <th class="px-3 py-2 text-left">Adresse</th>
                <th class="px-3 py-2 text-right">Dette</th>
                <th class="px-3 py-2 text-right">Réduction %</th>
                <th class="px-3 py-2 text-center">Action</th>
              </tr>
            </thead>
            <tbody v-if="displayFournisseurs.length">
              <tr v-for="f in displayFournisseurs" :key="(f as any).id ?? (f as any).uuid" class="border-t">
                <td class="px-3 py-2">{{ (f as any).nom_entreprise_fournisseur }}</td>
                <td class="px-3 py-2">{{ (f as any).adresse || '-' }}</td>
                <td class="px-3 py-2 text-right">{{ Number((f as any).dette || 0).toFixed(2) }}</td>
                <td class="px-3 py-2 text-right">{{ Number((f as any).reduction_pourcentage || 0).toFixed(2) }}</td>
                <td class="px-3 py-2 text-center">
                  <template v-if="offlineStore.isOnline">
                    <button type="button" @click="openFournisseurModal(f)" class="text-blue-600 hover:underline">Modifier</button>
                    <button type="button" @click="deleteFournisseur((f as any).id)" class="text-red-600 hover:underline ml-3">Supprimer</button>
                    <FeatureGate feature="dette_tracking" mode="inline">
                      <button type="button" @click="goFournisseurDetail((f as any).id)" class="text-gray-700 hover:underline ml-3">Détail</button>
                    </FeatureGate>
                  </template>
                  <span v-else class="text-gray-400 text-xs">hors-ligne</span>
                </td>
              </tr>
            </tbody>
            <tbody v-else>
              <tr>
                <td colspan="5" class="text-center py-6 text-gray-400">
                  {{ offlineStore.isOnline ? 'Aucun fournisseur trouvé' : 'Aucun fournisseur en cache local' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
	</div>
      </div>
    </div>

    <!-- Client Modal -->
    <div v-if="clientModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md" :key="clientForm.id || 'new'">
        <h2 class="text-xl font-bold mb-4">{{ clientForm.id ? 'Modifier' : 'Ajouter' }} un client</h2>

        <div class="space-y-2">
          <label class="text-sm text-gray-600">Nom du client</label>
          <input v-model="clientForm.nom_client" placeholder="Ex: Jean Mukendi" class="w-full border p-2 rounded" />
          <p v-if="clientForm.errors.nom_client" class="text-sm text-red-600">{{ clientForm.errors.nom_client }}</p>

          <label class="text-sm text-gray-600">Numéro de téléphone</label>
          <input v-model="clientForm.numero_telephone" placeholder="Ex: 0999999999" class="w-full border p-2 rounded" />
          <p v-if="clientForm.errors.numero_telephone" class="text-sm text-red-600">{{ clientForm.errors.numero_telephone }}</p>

          <label class="text-sm text-gray-600">Adresse</label>
          <input v-model="clientForm.adresse" placeholder="Adresse du client" class="w-full border p-2 rounded" />

          <label class="text-sm text-gray-600">Mot de passe Super Admin</label>
          <input v-model="clientForm.admin_password" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded" />
          <p v-if="clientForm.errors.admin_password" class="text-sm text-red-600">{{ clientForm.errors.admin_password }}</p>

          <div class="text-xs text-gray-500">
            Créance, achat mensuel et réduction accordée sont calculés automatiquement.
          </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="clientModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="submitClientForm" :disabled="clientForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60">
            {{ clientForm.processing ? 'En cours...' : 'Sauvegarder' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Fournisseur Modal -->
    <div v-if="fournisseurModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md" :key="fournisseurForm.id || 'new'">
        <h2 class="text-xl font-bold mb-4">{{ fournisseurForm.id ? 'Modifier' : 'Ajouter' }} un fournisseur</h2>

        <div class="space-y-2">
          <label class="text-sm text-gray-600">Nom du fournisseur</label>
          <input v-model="fournisseurForm.nom_entreprise_fournisseur" placeholder="Ex: Société ABC" class="w-full border p-2 rounded" />
          <p v-if="fournisseurForm.errors.nom_entreprise_fournisseur" class="text-sm text-red-600">{{ fournisseurForm.errors.nom_entreprise_fournisseur }}</p>

          <label class="text-sm text-gray-600">Adresse</label>
          <input v-model="fournisseurForm.adresse" placeholder="Adresse du fournisseur" class="w-full border p-2 rounded" />

          <label class="text-sm text-gray-600">Réduction accordée par le fournisseur (%)</label>
          <input v-model.number="fournisseurForm.reduction_pourcentage" type="number" step="0.01" placeholder="Ex: 5" class="w-full border p-2 rounded" />

          <label class="text-sm text-gray-600">Mot de passe Super Admin</label>
          <input v-model="fournisseurForm.admin_password" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded" />
          <p v-if="fournisseurForm.errors.admin_password" class="text-sm text-red-600">{{ fournisseurForm.errors.admin_password }}</p>

          <div class="text-xs text-gray-500">
            Dette, achat mensuel et réduction obtenue sont calculés automatiquement.
          </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="fournisseurModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="submitFournisseurForm" :disabled="fournisseurForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60">
            {{ fournisseurForm.processing ? 'En cours...' : 'Sauvegarder' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Client -->
    <div v-if="clientDeleteModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Confirmer la suppression</h2>
        <p class="text-sm text-gray-600 mb-3">Entrez le mot de passe Super Admin pour confirmer.</p>
        <input v-model="clientDeletePassword" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="clientDeleteModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="confirmDeleteClient" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
        </div>
      </div>
    </div>

    <!-- Delete Fournisseur -->
    <div v-if="fournisseurDeleteModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Confirmer la suppression</h2>
        <p class="text-sm text-gray-600 mb-3">Entrez le mot de passe Super Admin pour confirmer.</p>
        <input v-model="fournisseurDeletePassword" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="fournisseurDeleteModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="confirmDeleteFournisseur" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</template>
