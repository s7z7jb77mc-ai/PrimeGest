<script setup>
import { ref, computed } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'

// Props
const props = defineProps({
  employes: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  succursales: { type: Array, default: () => [] },
  has_succursales: { type: Boolean, default: false },
})

// Recherche
const search = ref(props.filters.search || '')
function handleSearch() {
  router.get('/employes', { search: search.value }, { preserveState: true, preserveScroll: true })
}

// Modal
const modalOpen = ref(false)
const deleteModalOpen = ref(false)
const deleteTargetId = ref(null)
const deletePassword = ref('')
const empty = {
  id: null,
  nom: '',
  prenom: '',
  email: '',
  telephone: '',
  poste: '',
  salaire_base: '',
  date_embauche: '',
  statut: 'actif',
  succursale_id: '',
  admin_password: '',
}

// Formulaire Inertia
const form = useForm({ ...empty })
const page = usePage()
const isSuperAdmin = computed(() => {
  const propsObj = page.props?.value ?? page.props ?? {}
  if (propsObj.auth?.user?.is_super_admin !== undefined) return propsObj.auth.user.is_super_admin === true
  if (propsObj.can_manage !== undefined) return propsObj.can_manage === true
  const role = String(propsObj.auth?.user?.role || '')
  return role.replace(/[\s-]+/g, '_').toLowerCase() === 'super_admin'
})
const t = _t
const lang = useLang()

function openModal(emp = null) {
  form.clearErrors()
  form.reset()
  if (emp) {
    Object.assign(form, {
      id: emp.id ?? null,
      nom: emp.nom ?? '',
      prenom: emp.prenom ?? '',
      email: emp.email ?? '',
      telephone: emp.telephone ?? '',
      poste: emp.poste ?? '',
      salaire_base: emp.salaire_base ?? '',
      date_embauche: emp.date_embauche ?? '',
      statut: emp.statut ?? 'actif',
      succursale_id: emp.succursale_id ?? '',
      admin_password: '',
    })
  } else {
    Object.assign(form, { ...empty })
  }
  modalOpen.value = true
}

function assignToSuccursale(emp) {
  form.clearErrors()
  form.reset()
  Object.assign(form, { ...emp })
  modalOpen.value = true
}

// Soumettre le formulaire
function submitForm() {
  if (form.id) {
    if (!form.admin_password) {
      alert('Mot de passe Super Admin requis.')
      return
    }
    form.put(`/employes/${form.id}`, {
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false },
    })
  } else {
    form.id = null
    form.post('/employes', {
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false },
    })
  }
}

// Supprimer un employé
function deleteEmploye(id) {
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
  router.delete(`/employes/${deleteTargetId.value}`, { data: { id: deleteTargetId.value, admin_password: deletePassword.value }, preserveScroll: true,
    onSuccess: () => { deleteModalOpen.value = false }
  })
}

// Aller au dashboard
function goDashboard() {
  router.get('/dashboard')
}
</script>

<template>
  <div class="p-6" :key="lang">

    <!-- Header -->
    <div class="flex justify-between mb-4">
      <h1 class="text-2xl font-bold">{{ t('employees') }}</h1>
      <div class="flex space-x-2">
        <button type="button" @click="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Ajouter un employé
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
        placeholder="Rechercher..."
        class="border p-2 rounded w-full md:w-1/3"
      />
    </div>

    <!-- Tableau des employés -->
    <div class="overflow-x-auto bg-white shadow rounded">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">Nom</th>
            <th class="px-3 py-2 text-left">Prénom</th>
            <th class="px-3 py-2 text-left">Email</th>
            <th class="px-3 py-2 text-left">Poste</th>
            <th class="px-3 py-2 text-left">Succursale</th>
            <th class="px-3 py-2 text-left">Salaire</th>
            <th class="px-3 py-2 text-left">Statut</th>
            <th class="px-3 py-2 text-left">Action</th>
          </tr>
        </thead>

        <tbody v-if="employes.length">
          <tr v-for="emp in employes" :key="emp.id" class="border-t">
            <td class="px-3 py-2">{{ emp.nom }}</td>
            <td class="px-3 py-2">{{ emp.prenom }}</td>
            <td class="px-3 py-2">{{ emp.email }}</td>
            <td class="px-3 py-2">{{ emp.poste }}</td>
            <td class="px-3 py-2">{{ emp.succursale?.nom || '-' }}</td>
            <td class="px-3 py-2">{{ emp.salaire_base }}</td>
            <td class="px-3 py-2">{{ emp.statut }}</td>
            <td class="px-3 py-2">
              <div class="flex gap-3">
                <button type="button" @click="openModal(emp)" class="text-blue-600 hover:underline">Modifier</button>
                <button v-if="props.has_succursales" type="button" @click="assignToSuccursale(emp)" class="text-indigo-600 hover:underline">Affecter à succursale</button>
                <button type="button" @click="deleteEmploye(emp.id)" class="text-red-600 hover:underline">Supprimer</button>
              </div>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr>
            <td colspan="8" class="text-center py-6 text-gray-400">Aucun employé trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
      <div class="bg-white p-6 rounded w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">{{ form.id ? 'Modifier' : 'Ajouter' }} un employé</h2>

        <div class="space-y-2">
          <input v-model="form.nom" placeholder="Nom" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.nom" class="text-red-600 text-sm">{{ form.errors.nom }}</p>

          <input v-model="form.prenom" placeholder="Prénom" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.prenom" class="text-red-600 text-sm">{{ form.errors.prenom }}</p>

          <input v-model="form.email" type="email" placeholder="Email" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.email" class="text-red-600 text-sm">{{ form.errors.email }}</p>

          <input v-model="form.telephone" placeholder="Téléphone" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.telephone" class="text-red-600 text-sm">{{ form.errors.telephone }}</p>

          <input v-model="form.poste" placeholder="Poste" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.poste" class="text-red-600 text-sm">{{ form.errors.poste }}</p>

          <input v-model.number="form.salaire_base" type="number" placeholder="Salaire de base" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.salaire_base" class="text-red-600 text-sm">{{ form.errors.salaire_base }}</p>

          <input v-model="form.date_embauche" type="date" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.date_embauche" class="text-red-600 text-sm">{{ form.errors.date_embauche }}</p>

          <select v-model="form.statut" class="w-full border p-2 rounded">
            <option value="actif">Actif</option>
            <option value="inactif">Inactif</option>
          </select>
          <p v-if="form.errors.statut" class="text-red-600 text-sm">{{ form.errors.statut }}</p>

          <div v-if="props.has_succursales">
            <label class="block text-sm text-gray-600 mb-1">Succursale</label>
            <select v-model="form.succursale_id" class="w-full border p-2 rounded">
              <option value="">-- Aucune --</option>
              <option v-for="s in props.succursales" :key="s.id" :value="s.id">{{ s.nom }}</option>
            </select>
            <p v-if="form.errors.succursale_id" class="text-red-600 text-sm">{{ form.errors.succursale_id }}</p>
          </div>

          <input v-model="form.admin_password" type="password" placeholder="Mot de passe Super Admin" class="w-full border p-2 rounded"/>
          <p v-if="form.errors.admin_password" class="text-red-600 text-sm">{{ form.errors.admin_password }}</p>
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
