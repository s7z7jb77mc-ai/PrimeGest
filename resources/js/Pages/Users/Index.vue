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
              <div v-else class="text-sm text-gray-700">
                {{ accessSummary(user) }}
              </div>
            </td>
            <td class="px-3 py-2">{{ user.employe?.nom ?? '—' }}</td>
            <td class="px-3 py-2">
              <div class="flex gap-3">
                <button type="button" @click="openModal(user)" class="text-blue-600 hover:underline">Modifier</button>
                <button type="button" @click="deleteUser(user.id)" :disabled="deletingId === user.id" class="text-red-600 hover:underline disabled:opacity-50">
                  <span v-if="deletingId === user.id">Suppression...</span>
                  <span v-else>Supprimer</span>
                </button>
              </div>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr>
            <td colspan="5" class="text-center py-6 text-gray-400">Aucun utilisateur trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <form @submit.prevent="submitForm">
          <h2 class="text-xl font-bold mb-4">{{ form.id ? 'Modifier' : 'Ajouter' }} un utilisateur</h2>

          <div class="space-y-2">
            <!-- Employé -->
            <select v-model="form.employe_id" @change="autoFillEmploye" class="w-full border p-2 rounded">
              <option disabled value="">Associer à un employé</option>
              <option v-for="emp in employes" :key="emp.id" :value="emp.id">{{ emp.nom }}</option>
            </select>
            <p v-if="form.errors.employe_id" class="text-red-600 text-sm">{{ form.errors.employe_id }}</p>

            <!-- Nom / Email -->
            <input v-model="form.name" placeholder="Nom" class="w-full border p-2 rounded"/>
            <input v-model="form.email" placeholder="Email" class="w-full border p-2 rounded"/>

            <!-- Rôle -->
            <select v-model="form.role" class="w-full border p-2 rounded">
              <option disabled value="">Choisir un rôle</option>
              <option v-for="role in availableRoles" :key="role.value" :value="role.value">
                 {{ role.label }}
              </option>

            </select>
            <p v-if="form.errors.role" class="text-red-600 text-sm">{{ form.errors.role }}</p>

            <!-- Accès -->
            <div>
              <label class="text-sm text-gray-600 mb-1 block">Accès aux pages</label>
              <div class="grid grid-cols-2 gap-2">
                <label v-for="opt in optionsForRole(form.role)" :key="opt.key" class="flex items-center gap-2 text-sm">
                  <input type="checkbox" :value="opt.key" v-model="form.access_pages" />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
              <p v-if="form.errors.access_pages" class="text-red-600 text-sm">{{ form.errors.access_pages }}</p>
            </div>

            <!-- Mot de passe -->
            <input v-model="form.password" type="password" placeholder="Mot de passe (optionnel)" class="w-full border p-2 rounded"/>
            <p v-if="form.errors.password" class="text-red-600 text-sm">{{ form.errors.password }}</p>

            <input v-model="form.password_confirmation" type="password" placeholder="Confirmer le mot de passe" class="w-full border p-2 rounded"/>

            <!-- Mot de passe Super Admin -->
            <input v-model="form.admin_password" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded"/>
            <p v-if="form.errors.admin_password" class="text-red-600 text-sm">{{ form.errors.admin_password }}</p>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="modalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-60">
              {{ form.processing ? 'En cours...' : 'Sauvegarder' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal suppression -->
    <div v-if="deleteModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Confirmer la suppression</h2>
        <p class="text-sm text-gray-600 mb-3">Entrez le mot de passe Super Admin pour confirmer.</p>
        <input v-model="deletePassword" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded"/>
        <div class="mt-4 flex justify-end gap-2">
          <button type="button" @click="deleteModalOpen = false" class="px-4 py-2 border rounded hover:bg-gray-100">Annuler</button>
          <button type="button" @click="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

const props = defineProps({
  users: { type: Array, default: () => [] },
  
  employes: { type: Array, default: () => [] },
  availableRoles: { type: Array, default: () => [] }
})
const page = usePage()
const isSuperAdmin = computed(() => {
  const propsObj = page.props?.value ?? page.props ?? {}
  const user = propsObj.auth?.user
  if (user?.is_super_admin !== undefined) return user.is_super_admin === true
  if (propsObj.can_manage !== undefined) return propsObj.can_manage === true
  const role = String(user?.role || '')
  return role.replace(/[\s-]+/g, '_').toLowerCase() === 'super_admin' || role.replace(/[\s-]+/g, '_').toLowerCase() === 'super_aadmin'
})
const t = _t
const lang = useLang()

// Tableau réactif pour mise à jour après suppression
const users = ref([...props.users])

// id en cours de suppression (pour désactiver le bouton)
const deletingId = ref(null)
const deleteModalOpen = ref(false)
const deleteTargetId = ref(null)
const deletePassword = ref('')
const accessSelections = ref({})

const allAccessOptions = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'mouvement_stocks', label: 'Mouvement stock' },
  { key: 'produits', label: 'Produits' },
  { key: 'tiers', label: 'Tiers' },
  { key: 'journal', label: 'Journal' },
  { key: 'factures', label: 'Factures' },
  { key: 'rapports', label: 'Rapports' },
  { key: 'archives', label: 'Archives' },
  { key: 'caisse', label: 'Caisse' },
  { key: 'creances_dettes', label: 'Créances & dettes' },
  { key: 'ressources_humaines', label: 'Ressources humaines' },
  { key: 'succursales', label: 'Succursales' },
  { key: 'transferts', label: 'Transferts' },
  { key: 'users', label: 'Utilisateurs' },
  { key: 'parametres', label: 'Paramètres' },
]


// Modal
const modalOpen = ref(false)
const empty = {
  id: null,
  name: '',
  email: '',
  role: '',
  employe_id: '',
  access_pages: [],
  password: '',
  password_confirmation: '',
  admin_password: ''
}

// Formulaire Inertia
const form = useForm({ ...empty })

onMounted(() => {})

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

function openModal(user = null) {
  form.clearErrors()
  form.reset()
  if (user) {
    Object.assign(form, {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      employe_id: user.employe_id || '',
      access_pages: Array.isArray(user.access_pages) ? [...user.access_pages] : [],
      password: '',
      password_confirmation: '',
      admin_password: ''
    })
  } else {
    Object.assign(form, { ...empty })
  }
  modalOpen.value = true
}

function accessLabel(role) {
  const r = String(role || '').toLowerCase()
  if (r === 'super_admin') return 'Complet'
  if (r === 'admin') return 'Opérations'
  if (r === 'user') return 'Lecture'
  return role || '-'
}

function optionsForRole(role) {
  return allAccessOptions
}

function accessSummary(user) {
  const r = String(user.role || '').toLowerCase()
  const options = optionsForRole(user.role)
  const selected = accessSelections.value[user.id] || []
  if (!selected.length) return r === 'user' ? 'Lecture: Aucun accès' : 'Écriture: Aucun accès'
  const status = selected.length >= options.length ? 'Complet' : 'Partiel'
  return r === 'user' ? `Lecture: ${status}` : `Écriture: ${status}`
}

function selectedLabels(user) {
  const selected = accessSelections.value[user.id] || []
  const options = optionsForRole(user.role)
  return options.filter(o => selected.includes(o.key)).map(o => o.label)
}

function updateAccess(user) {
  const pages = accessSelections.value[user.id] || []
  router.post(`/users/${user.id}/access`, { access_pages: pages }, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['users'] })
  })
}


function autoFillEmploye() {
  const emp = props.employes.find(e => e.id === form.employe_id)
  if (emp) {
    form.name = emp.nom
    form.email = emp.email
  }
}

function submitForm() {
  if (form.id) {
    if (!form.admin_password) {
      alert('Mot de passe Super Admin requis.')
      return
    }
    form.put(`/users/${form.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        modalOpen.value = false
        router.reload({ only: ['users'] })
      },
      onError: (errors) => {
        alert(Object.values(errors).join('\n') || 'Erreur lors de la modification.')
      }
    })
  } else {
    if (!form.admin_password) {
      alert('Mot de passe Super Admin requis.')
      return
    }
    form.post('/users', {
      preserveScroll: true,
      onSuccess: () => {
        modalOpen.value = false
        router.reload({ only: ['users'] })
      },
      onError: (errors) => {
        alert(Object.values(errors).join('\n') || 'Erreur lors de la création.')
      }
    })
  }
}

watch(
  () => form.role,
  (role) => {
    const allowed = optionsForRole(role).map(o => o.key)
    form.access_pages = (form.access_pages || []).filter(k => allowed.includes(k))
  }
)

function deleteUser(id) {
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

  deletingId.value = deleteTargetId.value
  router.delete(`/users/${deleteTargetId.value}`, {
    data: { admin_password: deletePassword.value },
    preserveScroll: true,
    onSuccess: (page) => {
      users.value = users.value.filter(u => u.id !== deleteTargetId.value)
      deleteModalOpen.value = false
    },
    onError: (errors) => {
      alert('Erreur lors de la suppression. Vérifiez la console pour plus de détails.')
    },
    onFinish: () => {
      if (deletingId.value === deleteTargetId.value) deletingId.value = null
    }
  })
}

function goDashboard() {
  router.get('/dashboard')
}
</script>
