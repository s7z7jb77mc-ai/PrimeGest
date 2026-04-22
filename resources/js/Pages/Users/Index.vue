<template>
  <div class="p-6" :key="lang">

    <!-- Header -->
    <div class="flex justify-between mb-4">
      <h1 class="text-2xl font-bold">{{ t('users') }}</h1>
      <div class="flex space-x-2">
        <button type="button" @click="openModal()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
          Ajouter un utilisateur
        </button>
        <button type="button" @click="goDashboard" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
          {{ t('dashboard') }}
        </button>
      </div>
    </div>

    <!-- Tableau des utilisateurs -->
    <div class="overflow-x-auto bg-white shadow rounded">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">Nom</th>
            <th class="px-3 py-2 text-left">Email</th>
            <th class="px-3 py-2 text-left">Rôle</th>
            <th class="px-3 py-2 text-left">Accès</th>
            <th class="px-3 py-2 text-left">Employé</th>
            <th class="px-3 py-2 text-left">Action</th>
          </tr>
        </thead>
        <tbody v-if="users.length">
          <tr v-for="user in users" :key="user.id" class="border-t">
            <td class="px-3 py-2">{{ user.name }}</td>
            <td class="px-3 py-2">{{ user.email }}</td>
            <td class="px-3 py-2 capitalize">{{ user.role }}</td>
            <td class="px-3 py-2">
              <div v-if="String(user.role || '').toLowerCase() === 'super_admin'">Complet</div>
              <div v-else class="text-sm text-gray-700">{{ accessSummary(user) }}</div>
            </td>
            <td class="px-3 py-2">{{ user.employe?.nom ?? '—' }}</td>
            <td class="px-3 py-2">
              <div class="flex gap-3">
                <button type="button" @click="openModal(user)" class="text-blue-600 hover:underline">Modifier</button>
                <button type="button" @click="deleteUser(user.id)" :disabled="deletingId === user.id"
                  class="text-red-600 hover:underline disabled:opacity-50">
                  <span v-if="deletingId === user.id">Suppression...</span>
                  <span v-else>Supprimer</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr>
            <td colspan="6" class="text-center py-6 text-gray-400">Aucun utilisateur trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal ajout/modification -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-start pt-10 z-50 px-4">
      <div class="bg-white p-6 rounded-xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-xl">
        <form @submit.prevent="submitForm">
          <h2 class="text-xl font-bold mb-4">{{ form.id ? 'Modifier' : 'Ajouter' }} un utilisateur</h2>

          <!-- Bandeau erreur global -->
          <div v-if="globalError" class="mb-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm whitespace-pre-line">
            {{ globalError }}
          </div>

          <div class="space-y-3">
            <!-- Employé -->
            <div>
              <label class="text-sm text-gray-600 block mb-1">Associer à un employé (optionnel)</label>
              <select v-model="form.employe_id" @change="autoFillEmploye" class="w-full border p-2 rounded">
                <option value="">— Aucun employé —</option>
                <option v-for="emp in employes" :key="emp.id" :value="emp.id">{{ emp.nom }}</option>
              </select>
              <p v-if="form.errors.employe_id" class="text-red-600 text-xs mt-1">{{ form.errors.employe_id }}</p>
            </div>

            <!-- Nom -->
            <div>
              <label class="text-sm text-gray-600 block mb-1">Nom *</label>
              <input v-model="form.name" placeholder="Nom complet" class="w-full border p-2 rounded" />
              <p v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <!-- Email -->
            <div>
              <label class="text-sm text-gray-600 block mb-1">Email *</label>
              <input v-model="form.email" type="email" placeholder="email@exemple.com" class="w-full border p-2 rounded" />
              <p v-if="form.errors.email" class="text-red-600 text-xs mt-1">{{ form.errors.email }}</p>
            </div>

            <!-- Rôle — utilise availableRoles du controller (pas de super_admin en succursale) -->
            <div>
              <label class="text-sm text-gray-600 block mb-1">Rôle *</label>
              <select v-model="form.role" class="w-full border p-2 rounded">
                <option disabled value="">Choisir un rôle</option>
                <option v-for="role in availableRoles" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>
              <p v-if="form.errors.role" class="text-red-600 text-xs mt-1">{{ form.errors.role }}</p>
            </div>

            <!-- Accès aux pages (masqué pour super_admin) -->
            <div v-if="form.role && form.role !== 'super_admin'">
              <label class="text-sm text-gray-600 mb-1 block">Accès aux pages</label>
              <div class="grid grid-cols-2 gap-2 border rounded p-3 bg-gray-50">
                <label v-for="opt in allAccessOptions" :key="opt.key" class="flex items-center gap-2 text-sm cursor-pointer">
                  <input type="checkbox" :value="opt.key" v-model="form.access_pages" />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
              <p v-if="form.errors.access_pages" class="text-red-600 text-xs mt-1">{{ form.errors.access_pages }}</p>
            </div>

            <!-- Mot de passe -->
            <div>
              <label class="text-sm text-gray-600 block mb-1">
                Mot de passe {{ form.id ? '(laisser vide pour ne pas changer)' : '*' }}
              </label>
              <input v-model="form.password" type="password"
                :placeholder="form.id ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe min. 6 caractères'"
                class="w-full border p-2 rounded" />
              <p v-if="form.errors.password" class="text-red-600 text-xs mt-1">{{ form.errors.password }}</p>
            </div>

            <div v-if="form.password || !form.id">
              <label class="text-sm text-gray-600 block mb-1">Confirmer le mot de passe *</label>
              <input v-model="form.password_confirmation" type="password"
                placeholder="Répéter le mot de passe" class="w-full border p-2 rounded" />
            </div>

            <!-- Mot de passe de confirmation (super admin ou manager) — TOUJOURS requis -->
            <div class="border-t pt-3 mt-1">
              <label class="text-sm font-medium text-gray-700 block mb-1">
                Votre mot de passe *
              </label>
              <input v-model="form.admin_password" type="password"
                placeholder="Entrez votre propre mot de passe de connexion"
                class="w-full border p-2 rounded border-orange-300 bg-orange-50" />
              <p class="text-xs text-gray-400 mt-1">Requis pour confirmer cette action (Super Admin ou Manager).</p>
              <p v-if="form.errors.admin_password" class="text-red-600 text-xs mt-1">{{ form.errors.admin_password }}</p>
            </div>
          </div>

          <div class="mt-5 flex justify-end gap-2">
            <button type="button" @click="closeModal" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
            <button type="submit" :disabled="form.processing"
              class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-60">
              {{ form.processing ? 'En cours...' : (form.id ? 'Modifier' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal suppression -->
    <div v-if="deleteModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 px-4">
      <div class="bg-white p-6 rounded-xl w-full max-w-md shadow-xl">
        <h2 class="text-xl font-bold mb-2">Confirmer la suppression</h2>
        <p class="text-sm text-gray-600 mb-3">Entrez votre mot de passe pour confirmer.</p>
        <input v-model="deletePassword" type="password"
          placeholder="Votre mot de passe de connexion"
          class="w-full border p-2 rounded" />
        <p v-if="deleteError" class="text-red-600 text-sm mt-2">{{ deleteError }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="deleteModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

// ── Props depuis le controller ─────────────────────────────────────
const props = defineProps({
  users:          { type: Array, default: () => [] },
  employes:       { type: Array, default: () => [] },
  // availableRoles vient du controller — pas de super_admin en succursale
  availableRoles: { type: Array, default: () => [
    { value: 'super_admin', label: 'Super Admin' },
    { value: 'admin',       label: 'Admin' },
    { value: 'user',        label: 'Utilisateur' },
  ]},
})

const t    = _t
const lang = useLang()

// ── State ──────────────────────────────────────────────────────────
const users          = ref([...props.users])
const deletingId     = ref(null)
const deleteModalOpen  = ref(false)
const deleteTargetId = ref(null)
const deletePassword = ref('')
const deleteError    = ref('')
const accessSelections = ref({})
const modalOpen      = ref(false)
const globalError    = ref('')

const allAccessOptions = [
  { key: 'dashboard',           label: 'Dashboard' },
  { key: 'mouvement_stocks',    label: 'Mouvement stock' },
  { key: 'produits',            label: 'Produits' },
  { key: 'tiers',               label: 'Tiers' },
  { key: 'journal',             label: 'Journal' },
  { key: 'factures',            label: 'Factures' },
  { key: 'rapports',            label: 'Rapports' },
  { key: 'archives',            label: 'Archives' },
  { key: 'caisse',              label: 'Caisse' },
  { key: 'creances_dettes',     label: 'Créances & dettes' },
  { key: 'ressources_humaines', label: 'Ressources humaines' },
  { key: 'succursales',         label: 'Succursales' },
  { key: 'transferts',          label: 'Transferts' },
  { key: 'users',               label: 'Utilisateurs' },
  { key: 'parametres',          label: 'Paramètres' },
]

const empty = {
  id:                    null,
  name:                  '',
  email:                 '',
  role:                  '',
  employe_id:            '',
  access_pages:          [],
  password:              '',
  password_confirmation: '',
  admin_password:        '',
}

const form = useForm({ ...empty })

// ── Sync users depuis props ────────────────────────────────────────
watch(
  () => props.users,
  (list) => {
    users.value = [...list]
    accessSelections.value = list.reduce((acc, u) => {
      acc[u.id] = Array.isArray(u.access_pages) ? [...u.access_pages] : []
      return acc
    }, {})
  },
  { immediate: true, deep: true }
)

// ── Fonctions ──────────────────────────────────────────────────────
function openModal(user = null) {
  form.clearErrors()
  form.reset()
  globalError.value = ''
  if (user) {
    Object.assign(form, {
      id:                    user.id,
      name:                  user.name,
      email:                 user.email,
      role:                  user.role,
      employe_id:            user.employe_id || '',
      access_pages:          Array.isArray(user.access_pages) ? [...user.access_pages] : [],
      password:              '',
      password_confirmation: '',
      admin_password:        '',
    })
  } else {
    Object.assign(form, { ...empty })
  }
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  globalError.value = ''
  form.reset()
}

function autoFillEmploye() {
  const emp = props.employes.find(e => e.id == form.employe_id)
  if (emp) {
    form.name  = emp.nom  || form.name
    form.email = emp.email || form.email
  }
}

function accessSummary(user) {
  const selected = accessSelections.value[user.id] || []
  if (!selected.length) return 'Aucun accès'
  return selected.length >= allAccessOptions.length ? 'Complet' : `${selected.length}/${allAccessOptions.length} pages`
}

function submitForm() {
  globalError.value = ''

  if (!form.admin_password) {
    globalError.value = 'Votre mot de passe est requis pour confirmer cette action.'
    return
  }

  if (form.id) {
    // Modification
    form.put(`/users/${form.id}`, {
      preserveScroll: true,
      onSuccess: () => { closeModal(); router.reload({ only: ['users'] }) },
      onError: (errors) => {
        globalError.value = Object.values(errors).join('\n') || 'Erreur lors de la modification.'
      },
    })
  } else {
    // Création
    form.post('/users', {
      preserveScroll: true,
      onSuccess: () => { closeModal(); router.reload({ only: ['users'] }) },
      onError: (errors) => {
        globalError.value = Object.values(errors).join('\n') || 'Erreur lors de la création.'
      },
    })
  }
}

function deleteUser(id) {
  deleteTargetId.value = id
  deletePassword.value = ''
  deleteError.value    = ''
  deleteModalOpen.value = true
}

function confirmDelete() {
  if (!deletePassword.value) {
    deleteError.value = 'Votre mot de passe est requis.'
    return
  }
  deletingId.value = deleteTargetId.value
  router.delete(`/users/${deleteTargetId.value}`, {
    data: { admin_password: deletePassword.value },
    preserveScroll: true,
    onSuccess: () => {
      users.value = users.value.filter(u => u.id !== deleteTargetId.value)
      deleteModalOpen.value = false
    },
    onError: (errors) => {
      deleteError.value = Object.values(errors).join('\n') || 'Mot de passe incorrect.'
    },
    onFinish: () => {
      if (deletingId.value === deleteTargetId.value) deletingId.value = null
    },
  })
}

function goDashboard() {
  router.get('/dashboard')
}
</script>
