<template>
  <div class="max-w-4xl mx-auto p-6 bg-white rounded-lg shadow space-y-6">
    <h2 class="text-2xl font-bold mb-6">Paramètres de l'entreprise</h2>

    <form @submit.prevent="submit" class="space-y-4">
      <!-- Logo -->
      <div>
        <label class="block text-gray-700 mb-1">Logo</label>
        <input type="file" @change="handleLogoUpload" class="block w-full border rounded p-2" />
        <img v-if="previewLogo" :src="previewLogo" alt="Logo" class="mt-2 h-16 object-contain" />
      </div>

      <!-- Nom -->
      <div>
        <label class="block text-gray-700 mb-1">Nom de l'entreprise</label>
        <input v-model="form.nom" type="text" class="w-full border rounded p-2" />
        <p v-if="form.errors.nom" class="text-red-600 text-sm">{{ form.errors.nom }}</p>
      </div>

      <!-- Adresse -->
      <div>
        <label class="block text-gray-700 mb-1">Adresse</label>
        <input v-model="form.adresse" type="text" class="w-full border rounded p-2" />
      </div>

      <!-- Email -->
      <div>
        <label class="block text-gray-700 mb-1">Email</label>
        <input v-model="form.email" type="email" class="w-full border rounded p-2" />
      </div>

      <!-- Téléphone -->
      <div>
        <label class="block text-gray-700 mb-1">Téléphone</label>
        <input v-model="form.telephone" type="text" class="w-full border rounded p-2" />
      </div>

      <!-- RCCM -->
      <div>
        <label class="block text-gray-700 mb-1">RCCM</label>
        <input v-model="form.rccm" type="text" class="w-full border rounded p-2" />
      </div>

      <!-- Identifiant national -->
      <div>
        <label class="block text-gray-700 mb-1">Identifiant national</label>
        <input v-model="form.identifiant_national" type="text" class="w-full border rounded p-2" />
      </div>

      <!-- Numéro impôt -->
      <div>
        <label class="block text-gray-700 mb-1">Numéro impôt</label>
        <input v-model="form.numero_impot" type="text" class="w-full border rounded p-2" />
      </div>

      <!-- Devise -->
      <div>
        <label class="block text-gray-700 mb-1">Devise</label>
        <select v-model="form.devise" class="w-full border rounded p-2">
          <option value="USD">USD ($ Dollar)</option>
          <option value="CDF">CDF (Franc Congolais)</option>
          <option value="RWF">RWF (Franc Rwandais)</option>
        </select>
      </div>

      <!-- Thème -->
      <div>
        <label class="block text-gray-700 mb-1">Thème</label>
        <div class="flex space-x-4">
          <button type="button"
                  @click="form.theme = 'clair'"
                  :class="form.theme === 'clair' ? 'bg-blue-500 text-white' : 'bg-gray-200'"
                  class="p-2 rounded flex items-center space-x-2">
            <Icon name="light_mode" /> <span>Clair</span>
          </button>
          <button type="button"
                  @click="form.theme = 'sombre'"
                  :class="form.theme === 'sombre' ? 'bg-blue-500 text-white' : 'bg-gray-200'"
                  class="p-2 rounded flex items-center space-x-2">
            <Icon name="dark_mode" /> <span>Sombre</span>
          </button>
        </div>
      </div>

      <!-- Sauvegarde auto DB -->
      <div class="flex items-center space-x-2">
        <input type="checkbox" v-model="form.auto_backup" id="auto_backup" />
        <label for="auto_backup" class="text-gray-700">Sauvegarde automatique de la base de données</label>
      </div>

      <!-- Historique des connexions -->
      <div>
        <h3 class="font-semibold text-gray-700 mb-2">Historique des connexions</h3>
        <ul class="border rounded p-2 max-h-40 overflow-y-auto text-sm">
          <li v-for="log in connectionLogs" :key="log.id" class="flex justify-between">
            <span>{{ log.user }}</span>
            <span class="text-gray-500">{{ log.time }}</span>
          </li>
          <li v-if="connectionLogs.length === 0" class="text-gray-400">Aucune connexion enregistrée</li>
        </ul>
      </div>

      <!-- Boutons -->
      <div class="flex justify-end space-x-4">
        <button type="button"
                @click="goDashboard"
                class="px-4 py-2 rounded border text-gray-700 hover:bg-gray-100">
          Annuler
        </button>
        <button type="submit"
                class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
          Sauvegarder
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3'
import Icon from '@/components/Icon.vue'
import { ref } from 'vue'

const props = defineProps({
  settings: Object,
  connectionLogs: { type: Array, default: () => [] },
})

const previewLogo = ref(props.settings.logo_url || null)

const form = useForm({
  nom: props.settings.nom || '',
  adresse: props.settings.adresse || '',
  email: props.settings.email || '',
  telephone: props.settings.telephone || '',
  rccm: props.settings.rccm || '',
  identifiant_national: props.settings.identifiant_national || '',
  numero_impot: props.settings.numero_impot || '',
  devise: props.settings.devise || 'CDF',
  theme: props.settings.theme || 'clair',
  auto_backup: props.settings.auto_backup || false,
  logo: null,
})

function handleLogoUpload(event) {
  const file = event.target.files[0]
  if (file) {
    form.logo = file
    previewLogo.value = URL.createObjectURL(file)
  }
}

function submit() {
  form.post('/parametres', {
    onSuccess: () => {
      router.visit('/dashboard')
    }
  })
}

function goDashboard() {
  router.visit('/dashboard')
}
</script>
