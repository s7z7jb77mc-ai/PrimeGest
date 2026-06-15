<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useLocalDB } from '@/composables/useLocalDB'
defineOptions({ layout: AppDashboardLayout })

const offlineStore = useOfflineStore()
const localDB = useLocalDB()

const props = defineProps({
  clients: { type: Array, default: () => [] },
  fournisseurs: { type: Array, default: () => [] },
  devise: { type: String, default: 'CDF' },
})

const devise = computed(() => props.devise || 'CDF')
const t = _t
const lang = useLang()

const montantClient = ref({})
const montantFournisseur = ref({})

// ── Données hors-ligne (Dexie) ────────────────────────────────────
// creance/dette sont de vraies colonnes synchronisées : on filtre
// localement comme le fait le contrôleur (creance > 0 / dette > 0).
const localClients = ref<any[]>([])
const localFournisseurs = ref<any[]>([])

async function loadLocalCreancesDettes() {
  if (!localDB.isAvailable) return
  const [clients, fournisseurs] = await Promise.all([
    localDB.getClients(),
    localDB.getFournisseurs(),
  ])
  localClients.value = clients.filter((c: any) => Number(c.creance || 0) > 0)
  localFournisseurs.value = fournisseurs.filter((f: any) => Number(f.dette || 0) > 0)
}

onMounted(async () => {
  if (!offlineStore.isOnline) await loadLocalCreancesDettes()
  window.addEventListener('primegest:sync-pulled', loadLocalCreancesDettes)
  window.addEventListener('primegest:offline', loadLocalCreancesDettes)
  window.addEventListener('primegest:local-write', loadLocalCreancesDettes)
})

onUnmounted(() => {
  window.removeEventListener('primegest:sync-pulled', loadLocalCreancesDettes)
  window.removeEventListener('primegest:offline', loadLocalCreancesDettes)
  window.removeEventListener('primegest:local-write', loadLocalCreancesDettes)
})

const isOffline = computed(() => !offlineStore.isOnline)

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

// Clé stable : l'id numérique est absent hors-ligne (uniquement uuid)
function rowKey(entity: any): string {
  return String(entity.uuid ?? entity.id)
}

// Le paiement et le détail exigent le serveur (id numérique + écriture
// caisse). Désactivés hors-ligne pour éviter une action qui échouerait.
function payerCreance(client: any) {
  if (isOffline.value) return
  const montant = Number(montantClient.value[rowKey(client)] || 0)
  if (montant <= 0) {
    alert('Montant invalide.')
    return
  }
  router.post(`/creances-dettes/clients/${client.id}/paiement`, { montant }, { preserveScroll: true })
}

function payerDette(fournisseur: any) {
  if (isOffline.value) return
  const montant = Number(montantFournisseur.value[rowKey(fournisseur)] || 0)
  if (montant <= 0) {
    alert('Montant invalide.')
    return
  }
  router.post(`/creances-dettes/fournisseurs/${fournisseur.id}/paiement`, { montant }, { preserveScroll: true })
}

function goDashboard() {
  router.get('/dashboard')
}
</script>

<template>
  <div class="p-6 space-y-6" :key="lang">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold">{{ t('debts') }}</h1>
      <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        {{ t('dashboard') }}
      </button>
    </div>

    <div v-if="isOffline" class="bg-amber-50 border border-amber-200 text-amber-800 rounded p-3 text-sm">
      Mode hors-ligne — montants à la dernière synchronisation. Les paiements seront disponibles au retour de la connexion.
    </div>

    <div class="bg-white shadow rounded p-4">
      <h2 class="text-lg font-semibold mb-2">Créances clients</h2>
      <div class="overflow-x-auto">
	<div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Client</th>
              <th class="px-4 py-2 text-left">Téléphone</th>
              <th class="px-4 py-2 text-right">Créance</th>
              <th class="px-4 py-2 text-right">Montant payé</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody v-if="displayClients.length">
            <tr v-for="c in displayClients" :key="rowKey(c)" class="border-t">
              <td class="px-4 py-2">{{ c.nom_client }}</td>
              <td class="px-4 py-2">{{ c.numero_telephone }}</td>
              <td class="px-4 py-2 text-right">{{ Number(c.creance || 0).toFixed(2) }} {{ devise }}</td>
              <td class="px-4 py-2 text-right">
                <input v-model.number="montantClient[rowKey(c)]" type="number" step="0.01" :disabled="isOffline" class="w-32 border p-1 rounded disabled:bg-gray-100" />
              </td>
              <td class="px-4 py-2 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button type="button" @click="payerCreance(c)" :disabled="isOffline" class="px-3 py-1 bg-green-600 text-white rounded disabled:opacity-40 disabled:cursor-not-allowed">Payer</button>
                  <button type="button" @click="router.get(`/creances-dettes/clients/${c.id}`)" :disabled="isOffline" class="px-3 py-1 bg-blue-600 text-white rounded disabled:opacity-40 disabled:cursor-not-allowed">Détail</button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="5" class="text-center py-6 text-gray-400">Aucune créance</td>
            </tr>
          </tbody>
        </table>
      </div>
     </div>
    </div>

    <div class="bg-white shadow rounded p-4">
      <h2 class="text-lg font-semibold mb-2">Dettes fournisseurs</h2>
      <div class="overflow-x-auto">
	<div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Fournisseur</th>
              <th class="px-4 py-2 text-right">Dette</th>
              <th class="px-4 py-2 text-right">Montant payé</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody v-if="displayFournisseurs.length">
            <tr v-for="f in displayFournisseurs" :key="rowKey(f)" class="border-t">
              <td class="px-4 py-2">{{ f.nom_entreprise_fournisseur }}</td>
              <td class="px-4 py-2 text-right">{{ Number(f.dette || 0).toFixed(2) }} {{ devise }}</td>
              <td class="px-4 py-2 text-right">
                <input v-model.number="montantFournisseur[rowKey(f)]" type="number" step="0.01" :disabled="isOffline" class="w-32 border p-1 rounded disabled:bg-gray-100" />
              </td>
              <td class="px-4 py-2 text-center">
                <div class="flex items-center justify-center gap-2">
                  <button type="button" @click="payerDette(f)" :disabled="isOffline" class="px-3 py-1 bg-red-600 text-white rounded disabled:opacity-40 disabled:cursor-not-allowed">Payer</button>
                  <button type="button" @click="router.get(`/creances-dettes/fournisseurs/${f.id}`)" :disabled="isOffline" class="px-3 py-1 bg-blue-600 text-white rounded disabled:opacity-40 disabled:cursor-not-allowed">Détail</button>
                </div>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="4" class="text-center py-6 text-gray-400">Aucune dette</td>
            </tr>
          </tbody>
        </table>
	</div>
      </div>
    </div>
  </div>
</template>
