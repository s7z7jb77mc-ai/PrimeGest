<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useOfflineQueue } from '@/composables/useOfflineQueue'
import { useLocalDB } from '@/composables/useLocalDB'
defineOptions({ layout: AppDashboardLayout })

const props = defineProps({
  produits: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) }
})

// Recherche
const search = ref(props.filters.search || '')
function handleSearch() {
  router.get('/produits', { search: search.value }, { preserveState: true, preserveScroll: true })
}

// ─── Édition ────────────────────────────────────────────────────────────────
const editModalOpen = ref(false)
const editForm = useForm({
  id: null as number | null,
  uuid: null as string | null,
  nom: '',
  prix_achat: '' as string | number,
  prix_vente: '' as string | number,
  seuil_stock: 0,
  admin_password: '',
})

function openEditModal(produit: any) {
  editForm.clearErrors()
  editForm.reset()
  editForm.id           = produit.id
  editForm.uuid         = produit.uuid ?? null
  editForm.nom          = produit.nom ?? ''
  editForm.prix_achat   = produit.prix_achat ?? ''
  editForm.prix_vente   = produit.prix_vente ?? ''
  editForm.seuil_stock  = produit.stock?.seuil_stock ?? 0
  editForm.admin_password = ''
  editModalOpen.value   = true
}

function submitEdit() {
  if (!offlineStore.isOnline) {
    const recordId = editForm.uuid ?? crypto.randomUUID()
    queueOperation('produits', recordId, 'update', {
      nom: editForm.nom,
      prix_achat: editForm.prix_achat,
      prix_vente: editForm.prix_vente,
    })
    editModalOpen.value = false
    alert('Hors ligne — modification sauvegardée, sync dès reconnexion.')
    return
  }
  if (!editForm.admin_password) {
    editForm.setError('admin_password', 'Mot de passe Super Admin requis.')
    return
  }
  editForm.put(`/produits/${editForm.id}`, {
    preserveScroll: true,
    onSuccess: () => { editModalOpen.value = false },
  })
}

// ─── Création en lot (batch) ─────────────────────────────────────────────────
type StagingItem = { nom: string; prix_achat: number; prix_vente: number; seuil_stock: number; stock_initial: number }

const batchModalOpen  = ref(false)
const stagingItem     = ref({ nom: '', prix_achat: '' as string | number, prix_vente: '' as string | number, seuil_stock: 0, stock_initial: 0 })
const stagingErrors   = ref<Record<string, string>>({})
const stagingList     = ref<StagingItem[]>([])
const batchForm       = useForm({ admin_password: '', produits: [] as StagingItem[] })

function openBatchModal() {
  resetStagingItem()
  stagingList.value = []
  batchForm.reset()
  batchModalOpen.value = true
}

function resetStagingItem() {
  stagingItem.value = { nom: '', prix_achat: '', prix_vente: '', seuil_stock: 0, stock_initial: 0 }
  stagingErrors.value = {}
}

function addToStaging() {
  stagingErrors.value = {}
  if (!String(stagingItem.value.nom).trim()) {
    stagingErrors.value.nom = 'Le nom est requis.'
    return
  }
  if (stagingItem.value.prix_vente === '' || Number(stagingItem.value.prix_vente) < 0) {
    stagingErrors.value.prix_vente = 'Prix de vente invalide.'
    return
  }
  if (stagingItem.value.prix_achat === '' || Number(stagingItem.value.prix_achat) < 0) {
    stagingErrors.value.prix_achat = "Prix d'achat invalide."
    return
  }
  stagingList.value.push({
    nom:           String(stagingItem.value.nom).trim(),
    prix_achat:    Number(stagingItem.value.prix_achat),
    prix_vente:    Number(stagingItem.value.prix_vente),
    seuil_stock:   Number(stagingItem.value.seuil_stock)   || 0,
    stock_initial: Number(stagingItem.value.stock_initial) || 0,
  })
  resetStagingItem()
}

function removeFromStaging(index: number) {
  stagingList.value.splice(index, 1)
}

function submitBatch() {
  if (stagingList.value.length === 0) return
  batchForm.produits = [...stagingList.value]
  batchForm.post('/produits/batch', {
    preserveScroll: true,
    onSuccess: () => {
      batchModalOpen.value = false
      stagingList.value    = []
    },
  })
}

// ─── Suppression ────────────────────────────────────────────────────────────
const deleteModalOpen   = ref(false)
const deleteTargetId    = ref<number | null>(null)
const deleteTargetUuid  = ref<string | null>(null)
const deletePassword    = ref('')

function deleteProduit(id: number, uuid?: string) {
  deleteTargetId.value   = id
  deleteTargetUuid.value = uuid ?? null
  deletePassword.value   = ''
  deleteModalOpen.value  = true
}

async function confirmDelete() {
  if (!deleteTargetId.value) return
  if (!offlineStore.isOnline) {
    const recordId = deleteTargetUuid.value ?? crypto.randomUUID()
    await queueOperation('produits', recordId, 'delete', {})
    deleteModalOpen.value = false
    alert('Hors ligne — suppression sauvegardée, sync dès reconnexion.')
    return
  }
  if (!deletePassword.value) {
    alert('Mot de passe Super Admin requis.')
    return
  }
  router.delete(`/produits/${deleteTargetId.value}`, {
    data: { id: deleteTargetId.value, admin_password: deletePassword.value },
    preserveScroll: true,
    onSuccess: () => { deleteModalOpen.value = false },
  })
}

// ─── Utilitaires ────────────────────────────────────────────────────────────
const page = usePage()
const _isSuperAdmin = computed(() => {
  const p = page.props?.value ?? page.props ?? {}
  if (p.auth?.user?.is_super_admin !== undefined) return p.auth.user.is_super_admin === true
  if (p.can_manage !== undefined) return p.can_manage === true
  return String(p.auth?.user?.role || '').replace(/[\s-]+/g, '_').toLowerCase() === 'super_admin'
})
const t    = _t
const lang = useLang()

const offlineStore       = useOfflineStore()
const { queueOperation } = useOfflineQueue()
const localDB            = useLocalDB()
const localProduits      = ref<any[]>([])

async function loadLocalProduits() {
  if (localDB.isAvailable) localProduits.value = await localDB.getProduits()
}

onMounted(async () => {
  if (!offlineStore.isOnline) await loadLocalProduits()
  window.addEventListener('primegest:sync-pulled', loadLocalProduits)
  window.addEventListener('primegest:offline',     loadLocalProduits)
  window.addEventListener('primegest:local-write', loadLocalProduits)
})

onUnmounted(() => {
  window.removeEventListener('primegest:sync-pulled', loadLocalProduits)
  window.removeEventListener('primegest:offline',     loadLocalProduits)
  window.removeEventListener('primegest:local-write', loadLocalProduits)
})

const displayProduits = computed<any[]>(() =>
  offlineStore.isOnline
    ? (props.produits as any[])
    : (localProduits.value.length > 0 ? localProduits.value : (props.produits as any[]))
)

function goDashboard() { router.get('/dashboard') }
</script>

<template>
  <div class="p-4 sm:p-6" :key="lang">

    <!-- Bannière hors-ligne -->
    <div v-if="!offlineStore.isOnline" class="mb-4 px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
      Mode hors-ligne — modifications sauvegardées localement, sync dès la reconnexion
    </div>

    <!-- En-tête -->
    <div class="flex flex-wrap justify-between gap-3 mb-4">
      <h1 class="text-2xl font-bold">{{ t('products') }}</h1>
      <div class="flex gap-2 flex-wrap">
        <button type="button" @click="openBatchModal" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Ajouter un produit
        </button>
        <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          {{ t('dashboard') }}
        </button>
      </div>
    </div>

    <!-- Recherche -->
    <div class="mb-4">
      <input
        v-model="search"
        @keyup.enter="handleSearch"
        type="text"
        placeholder="Rechercher un produit..."
        class="border p-2 rounded w-full md:w-1/3"
      />
    </div>

    <!-- Tableau principal -->
    <div class="overflow-x-auto bg-white shadow rounded">
      <div class="overflow-x-auto -mx-4 sm:mx-0">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Nom</th>
              <th class="px-4 py-2 text-right">Stock</th>
              <th class="px-4 py-2 text-right">Prix d'achat</th>
              <th class="px-4 py-2 text-right">Prix de vente</th>
              <th class="px-4 py-2 text-right">Seuil d'alerte</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody v-if="displayProduits.length">
            <tr v-for="prod in displayProduits" :key="(prod as any).id ?? (prod as any).uuid" class="border-t">
              <td class="px-4 py-2">{{ (prod as any).nom ?? (prod as any).nom_produit }}</td>
              <td class="px-4 py-2 text-right">{{ (prod as any).stock?.quantite ?? (prod as any).quantite ?? 0 }}</td>
              <td class="px-4 py-2 text-right">{{ (prod as any).prix_achat }}</td>
              <td class="px-4 py-2 text-right">{{ (prod as any).prix_vente }}</td>
              <td class="px-4 py-2 text-right">{{ (prod as any).stock?.seuil_stock ?? '-' }}</td>
              <td class="px-4 py-2 text-center">
                <template v-if="offlineStore.isOnline">
                  <button type="button" @click="openEditModal(prod)" class="text-blue-600 hover:underline">Modifier</button>
                  <button type="button" @click="deleteProduit((prod as any).id, (prod as any).uuid)" class="text-red-600 hover:underline ml-3">Supprimer</button>
                </template>
                <span v-else class="text-gray-400 text-xs">hors-ligne</span>
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr>
              <td colspan="6" class="text-center py-6 text-gray-400">
                {{ offlineStore.isOnline ? 'Aucun produit trouvé' : 'Aucun produit en cache local' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ Modal : Création en lot ════════════════════════════════════════════ -->
    <div v-if="batchModalOpen" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 py-6 px-4 overflow-y-auto">
      <div class="bg-white rounded-lg w-full max-w-2xl shadow-xl">

        <!-- En-tête modal -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h2 class="text-xl font-bold">Ajouter des produits</h2>
          <button type="button" @click="batchModalOpen = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <!-- Section 1 : formulaire de saisie -->
        <div class="px-6 py-4 border-b bg-gray-50">
          <p class="text-sm text-gray-500 mb-3">Remplissez les champs puis cliquez sur <strong>Ajouter à la liste</strong>.</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Nom -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom du produit <span class="text-red-500">*</span></label>
              <input
                v-model="stagingItem.nom"
                @keyup.enter="addToStaging"
                type="text"
                placeholder="Ex : Sucre 1 kg"
                class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="stagingErrors.nom ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="stagingErrors.nom" class="text-xs text-red-600 mt-1">{{ stagingErrors.nom }}</p>
            </div>

            <!-- Prix de vente -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Prix de vente <span class="text-red-500">*</span></label>
              <input
                v-model.number="stagingItem.prix_vente"
                type="number" step="0.01" min="0"
                placeholder="0"
                class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="stagingErrors.prix_vente ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="stagingErrors.prix_vente" class="text-xs text-red-600 mt-1">{{ stagingErrors.prix_vente }}</p>
            </div>

            <!-- Prix d'achat -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Prix d'achat <span class="text-red-500">*</span></label>
              <input
                v-model.number="stagingItem.prix_achat"
                type="number" step="0.01" min="0"
                placeholder="0"
                class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="stagingErrors.prix_achat ? 'border-red-400' : 'border-gray-300'"
              />
              <p v-if="stagingErrors.prix_achat" class="text-xs text-red-600 mt-1">{{ stagingErrors.prix_achat }}</p>
            </div>

            <!-- Seuil d'alerte -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte</label>
              <input
                v-model.number="stagingItem.seuil_stock"
                type="number" step="1" min="0"
                placeholder="0"
                class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            <!-- Stock initial (écriture unique) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Stock initial
                <span class="text-xs font-normal text-gray-400 ml-1">(non modifiable après création)</span>
              </label>
              <input
                v-model.number="stagingItem.stock_initial"
                type="number" step="1" min="0"
                placeholder="0"
                class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>

          <button
            type="button"
            @click="addToStaging"
            class="mt-4 px-5 py-2 bg-[#1A56A0] text-white rounded hover:bg-[#0B2D5E] font-medium"
          >
            + Ajouter à la liste
          </button>
        </div>

        <!-- Section 2 : tableau de staging -->
        <div class="px-6 py-4 border-b">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">
            Produits à créer
            <span v-if="stagingList.length" class="ml-2 bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ stagingList.length }}</span>
          </h3>

          <div v-if="stagingList.length === 0" class="text-sm text-gray-400 italic py-2">
            Aucun produit ajouté — utilisez le formulaire ci-dessus.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                  <th class="text-left px-3 py-2">Nom</th>
                  <th class="text-right px-3 py-2">P. vente</th>
                  <th class="text-right px-3 py-2">P. achat</th>
                  <th class="text-right px-3 py-2">Seuil</th>
                  <th class="text-right px-3 py-2">Stock init.</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(item, i) in stagingList"
                  :key="i"
                  class="border-t hover:bg-gray-50"
                >
                  <td class="px-3 py-2 font-medium">{{ item.nom }}</td>
                  <td class="px-3 py-2 text-right">{{ item.prix_vente }}</td>
                  <td class="px-3 py-2 text-right">{{ item.prix_achat }}</td>
                  <td class="px-3 py-2 text-right">{{ item.seuil_stock }}</td>
                  <td class="px-3 py-2 text-right">{{ item.stock_initial }}</td>
                  <td class="px-3 py-2 text-center">
                    <button type="button" @click="removeFromStaging(i)" class="text-red-500 hover:text-red-700 text-lg leading-none font-bold">&times;</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section 3 : confirmation mot de passe unique -->
        <div class="px-6 py-4">
          <p class="text-sm text-gray-600 mb-3">
            Entrez votre mot de passe Super Admin <strong>une seule fois</strong> pour créer tous les produits listés.
          </p>
          <input
            v-model="batchForm.admin_password"
            type="password"
            placeholder="Mot de passe Super Admin"
            class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
            :class="batchForm.errors.admin_password ? 'border-red-400' : 'border-gray-300'"
          />
          <p v-if="batchForm.errors.admin_password" class="text-xs text-red-600 mt-1">{{ batchForm.errors.admin_password }}</p>

          <!-- Erreurs batch générales -->
          <p v-if="batchForm.hasErrors && !batchForm.errors.admin_password" class="text-xs text-red-600 mt-1">
            Une erreur est survenue. Vérifiez les données et réessayez.
          </p>

          <div class="mt-4 flex justify-end gap-3">
            <button type="button" @click="batchModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">
              Annuler
            </button>
            <button
              type="button"
              @click="submitBatch"
              :disabled="batchForm.processing || stagingList.length === 0"
              class="px-5 py-2 bg-[#1A7A4A] text-white rounded hover:bg-green-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="batchForm.processing">Création en cours…</span>
              <span v-else-if="stagingList.length === 0">Aucun produit</span>
              <span v-else>Créer {{ stagingList.length }} produit{{ stagingList.length > 1 ? 's' : '' }}</span>
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- ═══ Modal : Édition ═════════════════════════════════════════════════════ -->
    <div v-if="editModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg w-full max-w-md shadow-xl" :key="editForm.id || 'edit'">
        <h2 class="text-xl font-bold mb-4">Modifier un produit</h2>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
            <input v-model="editForm.nom" type="text" class="w-full border border-gray-300 rounded px-3 py-2" />
            <p v-if="editForm.errors.nom" class="text-xs text-red-600 mt-1">{{ editForm.errors.nom }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Prix d'achat</label>
            <input v-model.number="editForm.prix_achat" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded px-3 py-2" />
            <p v-if="editForm.errors.prix_achat" class="text-xs text-red-600 mt-1">{{ editForm.errors.prix_achat }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Prix de vente</label>
            <input v-model.number="editForm.prix_vente" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded px-3 py-2" />
            <p v-if="editForm.errors.prix_vente" class="text-xs text-red-600 mt-1">{{ editForm.errors.prix_vente }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte</label>
            <input v-model.number="editForm.seuil_stock" type="number" step="1" min="0" class="w-full border border-gray-300 rounded px-3 py-2" />
            <p v-if="editForm.errors.seuil_stock" class="text-xs text-red-600 mt-1">{{ editForm.errors.seuil_stock }}</p>
          </div>

          <!-- Stock initial : affiché en lecture seule en mode édition -->
          <div class="bg-amber-50 border border-amber-200 rounded px-3 py-2 text-sm text-amber-700">
            Le stock initial est défini à la création et ne peut plus être modifié ici.<br>
            Pour ajuster le stock, utilisez les mouvements de stock.
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe Super Admin</label>
            <input v-model="editForm.admin_password" type="password" placeholder="Confirmer l'identité" class="w-full border rounded px-3 py-2"
              :class="editForm.errors.admin_password ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="editForm.errors.admin_password" class="text-xs text-red-600 mt-1">{{ editForm.errors.admin_password }}</p>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button type="button" @click="editModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button
            type="button"
            @click="submitEdit"
            :disabled="editForm.processing"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60"
          >
            {{ editForm.processing ? 'En cours…' : 'Sauvegarder' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Suppression ═════════════════════════════════════════════════ -->
    <div v-if="deleteModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg w-full max-w-md shadow-xl">
        <h2 class="text-xl font-bold mb-4">Confirmer la suppression</h2>
        <p class="text-sm text-gray-600 mb-3">Entrez le mot de passe Super Admin pour confirmer la suppression.</p>
        <input v-model="deletePassword" type="password" placeholder="Mot de passe Super Admin" class="w-full border border-gray-300 rounded px-3 py-2" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="deleteModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
        </div>
      </div>
    </div>

  </div>
</template>
