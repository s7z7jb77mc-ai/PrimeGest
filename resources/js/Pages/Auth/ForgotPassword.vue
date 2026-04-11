<template>
  <div class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-blue-100 px-4">
    <div class="w-full max-w-md p-8 bg-white/90 backdrop-blur-md rounded-2xl shadow-lg">
      <div class="text-center mb-6">
        <img src="/build/assets/primegest.png" alt="PrimeGest Logo" class="h-20 w-20 mx-auto mb-3"/>
        <h1 class="text-2xl font-bold text-blue-800">Mot de passe oublié</h1>
        <p class="text-sm text-gray-600 mt-1">Lien de réinitialisation réservé aux super admins</p>
      </div>

      <div v-if="status" class="mb-4 text-sm text-green-700 bg-green-100 border border-green-200 rounded p-3">
        {{ status }}
      </div>
      <div v-if="hasErrors" class="mb-4 text-sm text-red-700 bg-red-100 border border-red-200 rounded p-3">
        <div v-for="(msg, key) in errorList" :key="key">{{ msg }}</div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Entreprise</label>
          <input v-model="form.company_name" type="text" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.company_name" class="text-red-500 text-sm">{{ form.errors.company_name }}</span>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input v-model="form.email" type="email" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.email" class="text-red-500 text-sm">{{ form.errors.email }}</span>
        </div>

        <button type="submit" class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition-transform transform hover:scale-105">
          Réinitialiser le mot de passe
        </button>
      </form>

      <div class="mt-4 text-sm text-center">
        <a href="/login" class="text-blue-600 hover:underline">Retour à la connexion</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  status: String,
})

const form = useForm({
  company_name: '',
  email: '',
})

const page = usePage()
const errorList = computed(() => {
  const errs = page.props?.errors || page.props?.value?.errors || {}
  return Object.values(errs)
})
const hasErrors = computed(() => errorList.value.length > 0)

function submit() {
  form.post('/forgot-password')
}
</script>
