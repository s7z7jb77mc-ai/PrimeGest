<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useLocalDB } from '@/composables/useLocalDB'
defineOptions({ layout: AppDashboardLayout })

const offlineStore = useOfflineStore()
const localDB = useLocalDB()
const localSuccursales = ref<any[]>([])

async function loadLocalSuccursales() {
    localSuccursales.value = await localDB.getSuccursales()
}

onMounted(async () => {
    if (!offlineStore.isOnline) await loadLocalSuccursales()
    window.addEventListener('primegest:sync-pulled', loadLocalSuccursales)
})

const props = defineProps({
  succursales: { type: Array, default: () => [] },
})

const displaySuccursales = computed<any[]>(() =>
    offlineStore.isOnline ? (props.succursales as any[]) : localSuccursales.value
)

const page = usePage()
const user = computed(() => {
  const propsObj = page.props?.value ?? page.props ?? {}
  return propsObj.auth?.user || {}
})
const isSuperAdmin = computed(() => {
  const propsObj = page.props?.value ?? page.props ?? {}
  if (propsObj.can_manage === true) return true
  if (propsObj.can_manage === false) return false
  const role = String(user.value?.role || '')
  const normalized = role.replace(/[\s-]+/g, '_').toLowerCase()
  return user.value?.is_super_admin === true || normalized === 'super_admin' || normalized === 'super_aadmin'
})

const modalOpen = ref(false)
const deleteModalOpen = ref(false)
const deletingId = ref(null)
const deletePassword = ref('')
const form = ref({ id: null, nom: '', adresse: '', manager_user_id: '', admin_password: '' })
const errors = ref({})
const managers = page.props?.managers || page.props?.value?.managers || []

const openModal = (succursale = null) => {
  errors.value = {}
  if (succursale) {
    form.value = {
      id: succursale.id,
      nom: succursale.nom || '',
      adresse: succursale.adresse || '',
      manager_user_id: succursale.manager_user_id || '',
      admin_password: '',
    }
  } else {
    form.value = { id: null, nom: '', adresse: '', manager_user_id: '', admin_password: '' }
  }
  modalOpen.value = true
}

const closeModal = () => {
  modalOpen.value = false
}

const save = async () => {
  errors.value = {}
  if (!form.value.admin_password) {
    errors.value = { ...errors.value, admin_password: ['Mot de passe Super Admin requis.'] }
    return
  }
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const url = form.value.id ? `/succursales/${form.value.id}` : '/succursales'
    const method = form.value.id ? 'PUT' : 'POST'
    const response = await fetch(url, {
      method,
      headers: {
        'X-CSRF-TOKEN': csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(form.value),
    })
    const data = await response.json()
    if (!response.ok) {
      errors.value = data.errors || {}
      return
    }
    modalOpen.value = false
    router.reload({ only: ['succursales'] })
  } catch (e) {
    alert('Erreur: ' + e.message)
  }
}

const goDashboard = () => router.get('/dashboard')
const openSuccursale = (id) => router.get(`/succursales/${id}`)

const openDelete = (id) => {
  deletingId.value = id
  deletePassword.value = ''
  deleteModalOpen.value = true
}

const confirmDelete = async () => {
  if (!deletingId.value) return
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const response = await fetch(`/succursales/${deletingId.value}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ admin_password: deletePassword.value }),
    })
    if (!response.ok) {
      const data = await response.json()
      alert(data?.message || 'Erreur de suppression.')
      return
    }
    deleteModalOpen.value = false
    deletingId.value = null
    router.reload({ only: ['succursales'] })
  } catch (e) {
    alert('Erreur: ' + e.message)
  }
}
</script>

<template>
  <div class="p-6">
    <!-- Bannière hors-ligne -->
    <div v-if="!offlineStore.isOnline" class="mb-4 px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
      Mode hors-ligne — les succursales ne sont pas disponibles localement. Reconnectez-vous pour accéder à cette section.
    </div>

    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Succursales</h1>
      <div class="flex gap-2">
        <button v-if="isSuperAdmin" type="button" @click="openModal" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Nouvelle succursale
        </button>
        <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          Dashboard
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="s in displaySuccursales" :key="s.id" class="bg-white shadow rounded p-4">
        <div class="text-lg font-semibold">{{ s.nom }}</div>
        <div class="text-sm text-gray-600">{{ s.adresse || '-' }}</div>
        <div class="text-sm text-gray-600 mt-1">Manager: {{ s.manager || '-' }}</div>
        <div class="text-xs mt-2">
          <span :class="s.active ? 'text-green-700 bg-green-100' : 'text-gray-700 bg-gray-100'" class="px-2 py-1 rounded">
            {{ s.active ? 'Actif' : 'Inactif' }}
          </span>
        </div>
        <div class="mt-3 flex items-center gap-3">
          <button type="button" @click="openSuccursale(s.id)" class="text-blue-600 hover:underline">
            Ouvrir
          </button>
          <button v-if="isSuperAdmin" type="button" @click="openModal(s)" class="text-gray-700 hover:underline">
            Modifier
          </button>
          <button v-if="isSuperAdmin" type="button" @click="openDelete(s.id)" class="text-red-600 hover:underline">
            Supprimer
          </button>
        </div>
      </div>
      <div v-if="!displaySuccursales.length" class="text-gray-400">Aucune succursale enregistrée.</div>
    </div>

    <div v-if="modalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">{{ form.id ? 'Modifier succursale' : 'Nouvelle succursale' }}</h2>
        <div class="space-y-3">
          <div>
            <label class="block mb-1">Nom de la succursale</label>
            <input v-model="form.nom" class="input" type="text" />
            <p v-if="errors.nom" class="text-sm text-red-600">{{ errors.nom[0] }}</p>
          </div>
          <div>
            <label class="block mb-1">Adresse</label>
            <input v-model="form.adresse" class="input" type="text" />
          </div>
          <div>
            <label class="block mb-1">Nom manager (employé)</label>
            <select v-model="form.manager_user_id" class="input" required>
              <option value="">-- Sélectionner --</option>
              <option v-for="m in managers" :key="m.id" :value="m.id">
                {{ m.employe_nom || m.name }} ({{ m.role }})
              </option>
            </select>
            <p v-if="errors.manager_user_id" class="text-sm text-red-600">{{ errors.manager_user_id[0] }}</p>
          </div>
          <div>
            <label class="block mb-1">Mot de passe Super Admin</label>
            <input v-model="form.admin_password" type="password" class="input" />
            <p v-if="errors.admin_password" class="text-sm text-red-600">{{ errors.admin_password[0] }}</p>
          </div>
        </div>
        <div class="mt-4 flex justify-between">
          <button type="button" @click="closeModal" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
            Annuler
          </button>
          <button type="button" @click="save" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            OK
          </button>
        </div>
      </div>
    </div>

    <div v-if="deleteModalOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Supprimer la succursale</h2>
        <p class="text-sm text-gray-600 mb-3">Confirmez la suppression de cette succursale.</p>
        <div class="mb-3">
          <label class="block text-sm text-gray-600 mb-1">Mot de passe Super Admin</label>
          <input v-model="deletePassword" type="password" class="input" placeholder="Mot de passe" />
        </div>
        <div class="mt-4 flex justify-between">
          <button type="button" @click="deleteModalOpen = false" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">
            Annuler
          </button>
          <button type="button" @click="confirmDelete" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
            Supprimer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.input {
  @apply w-full border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-400;
}
</style>
