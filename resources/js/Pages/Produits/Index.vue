<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'   // ⟵ on utilise router ici (pas Inertia du package core)
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

// Props
const props = defineProps({
  produits: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) }
})

// Recherche
const search = ref(props.filters.search || '')
function handleSearch() {
  router.get('/produits', { search: search.value }, { preserveState: true, preserveScroll: true })
}

// Modal + form
const modalOpen = ref(false)
const deleteModalOpen = ref(false)
const deleteTargetId = ref(null)
const deletePassword = ref('')
const empty = { id: null, nom: '', prix_achat: '', prix_vente: '', seuil_stock: 0, admin_password: '' }
const form = useForm({ ...empty })
const page = usePage()
const _isSuperAdmin = computed(() => {
  const propsObj = page.props?.value ?? page.props ?? {}
  if (propsObj.auth?.user?.is_super_admin !== undefined) return propsObj.auth.user.is_super_admin === true
  if (propsObj.can_manage !== undefined) return propsObj.can_manage === true
  const role = String(propsObj.auth?.user?.role || '')
  return role.replace(/[\s-]+/g, '_').toLowerCase() === 'super_admin'
})
const t = _t
const lang = useLang()

function openModal(produit = null) {
  form.clearErrors()
  form.reset()
  if (produit) {
    // Remplissage explicite (important pour avoir form.id !)
    form.id = produit.id
    form.nom = produit.nom ?? ''
    form.prix_achat = produit.prix_achat ?? ''
    form.prix_vente = produit.prix_vente ?? ''
    form.seuil_stock = produit.stock?.seuil_stock ?? 0
    form.admin_password = ''
  } else {
    Object.assign(form, { ...empty })
  }
  modalOpen.value = true
}

function submitForm() {
  if (form.id) {
    if (!form.admin_password) {
      alert('Mot de passe Super Admin requis.')
      return
    }
    form.put(`/produits/${form.id}`, {
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false },
    })
  } else {
    form.post('/produits', {
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false },
    })
  }
}

function deleteProduit(id) {
  deleteTargetId.value = id
  deletePassword.value = ''
  deleteModalOpen.value = true
}

function confirmDelete() {
  if (!deleteTargetId.value) return
  if (!deletePassword.value) {
    alert('Mot de passe Super Admin requis.')
    return
  }
  router.delete(`/produits/${deleteTargetId.value}`, { data: { id: deleteTargetId.value, admin_password: deletePassword.value }, preserveScroll: true,
    onSuccess: () => { deleteModalOpen.value = false }
  })
}

function goDashboard() {
  router.get('/dashboard')
}
</script>

<template>
  <div class="p-6" :key="lang">
    <!-- Header -->
    <div class="flex justify-between mb-4">
      <h1 class="text-2xl font-bold">{{ t('products') }}</h1>
      <div class="flex gap-2">
        <button type="button" @click="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
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

    <!-- Tableau -->
    <div class="overflow-x-auto bg-white shadow rounded">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left">Nom</th>
            <th class="px-4 py-2 text-right">Stock</th>
            <th class="px-4 py-2 text-right">Prix d’achat</th>
            <th class="px-4 py-2 text-right">Prix de vente</th>
            <th class="px-4 py-2 text-right">Seuil d’alerte</th>
            <th class="px-4 py-2 text-center">Action</th>
          </tr>
        </thead>

        <tbody v-if="props.produits.length">
          <tr v-for="prod in props.produits" :key="prod.id" class="border-t">
            <td class="px-4 py-2">{{ prod.nom }}</td>
            <td class="px-4 py-2 text-right">{{ prod.stock?.quantite ?? 0 }}</td>
            <td class="px-4 py-2 text-right">{{ prod.prix_achat }}</td>
            <td class="px-4 py-2 text-right">{{ prod.prix_vente }}</td>
            <td class="px-4 py-2 text-right">{{ prod.stock?.seuil_stock ?? 0 }}</td>
            <td class="px-4 py-2 text-center">
              <button type="button" @click="openModal(prod)" class="text-blue-600 hover:underline">Modifier</button>
              <button type="button" @click="deleteProduit(prod.id)" class="text-red-600 hover:underline ml-3">Supprimer</button>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr>
            <td colspan="6" class="text-center py-6 text-gray-400">Aucun produit trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md" :key="form.id || 'new'">
        <h2 class="text-xl font-bold mb-4">{{ form.id ? 'Modifier' : 'Ajouter' }} un produit</h2>

        <div class="space-y-2">
          <input v-model="form.nom" placeholder="Nom" class="w-full border p-2 rounded" />
          <p v-if="form.errors.nom" class="text-sm text-red-600">{{ form.errors.nom }}</p>

          <input v-model.number="form.prix_achat" type="number" step="0.01" placeholder="Prix d’achat" class="w-full border p-2 rounded" />
          <p v-if="form.errors.prix_achat" class="text-sm text-red-600">{{ form.errors.prix_achat }}</p>

          <input v-model.number="form.prix_vente" type="number" step="0.01" placeholder="Prix de vente" class="w-full border p-2 rounded" />
          <p v-if="form.errors.prix_vente" class="text-sm text-red-600">{{ form.errors.prix_vente }}</p>

          <input v-model.number="form.seuil_stock" type="number" step="1" min="0" placeholder="Seuil d’alerte" class="w-full border p-2 rounded" />
          <p v-if="form.errors.seuil_stock" class="text-sm text-red-600">{{ form.errors.seuil_stock }}</p>

          <input v-model="form.admin_password" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded" />
          <p v-if="form.errors.admin_password" class="text-sm text-red-600">{{ form.errors.admin_password }}</p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="modalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button
            type="button"
            @click="submitForm"
            :disabled="form.processing"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60"
          >
            {{ form.processing ? 'En cours...' : 'Sauvegarder' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="deleteModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Confirmer la suppression</h2>
        <p class="text-sm text-gray-600 mb-3">Entrez le mot de passe Super Admin pour confirmer.</p>
        <input v-model="deletePassword" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded" />
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="deleteModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
        </div>
      </div>
    </div>
  </div>
</template>
