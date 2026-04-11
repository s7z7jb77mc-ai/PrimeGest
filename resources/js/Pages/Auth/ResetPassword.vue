<template>
  <div class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-blue-100 px-4">
    <div class="w-full max-w-md p-8 bg-white/90 backdrop-blur-md rounded-2xl shadow-lg">
      <div class="text-center mb-6">
        <img src="/build/assets/primegest.png" alt="PrimeGest Logo" class="h-20 w-20 mx-auto mb-3"/>
        <h1 class="text-2xl font-bold text-blue-800">Nouveau mot de passe</h1>
        <p class="text-sm text-gray-600 mt-1">Lien valable 30 minutes</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <input type="hidden" v-model="form.token" />
        <input type="hidden" v-model="form.email" />

        <div>
          <label class="block text-sm font-medium text-gray-700">Entreprise</label>
          <input v-model="form.company_name" type="text" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.company_name" class="text-red-500 text-sm">{{ form.errors.company_name }}</span>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Nouveau mot de passe</label>
          <input v-model="form.password" type="password" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.password" class="text-red-500 text-sm">{{ form.errors.password }}</span>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Confirmer le mot de passe</label>
          <input v-model="form.password_confirmation" type="password" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
        </div>

        <button type="submit" class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition-transform transform hover:scale-105">
          Réinitialiser
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  email: String,
  token: String,
  company_name: String,
})

const form = useForm({
  token: props.token || '',
  email: props.email || '',
  company_name: props.company_name || '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/reset-password')
}
</script>
