<template>
  <div class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-blue-100 px-4">

    <!-- Formulaire central -->
    <div class="w-full max-w-md p-8 bg-white/90 backdrop-blur-md rounded-2xl shadow-lg">
      <!-- Titre + logo -->
      <div class="text-center mb-6">
        <img src="/build/assets/primegest.png" alt="PrimeGest Logo" class="h-24 w-24 mx-auto mb-3"/>
        <h1 class="text-2xl font-bold text-blue-800">Connexion à PrimeGest</h1>
        <p class="text-sm text-gray-600 mt-1">Connectez-vous pour gérer votre entreprise</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div v-if="hasErrors" class="text-red-700 bg-red-100 border border-red-200 rounded p-3 text-sm">
          <div v-for="(msg, key) in errorList" :key="key">{{ msg }}</div>
        </div>
        <!-- Nom entreprise -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Entreprise</label>
          <input v-model="form.company_name" type="text" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.company_name" class="text-red-500 text-sm">{{ form.errors.company_name }}</span>
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input v-model="form.email" type="email" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.email" class="text-red-500 text-sm">{{ form.errors.email }}</span>
        </div>

        <!-- Mot de passe -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Mot de passe</label>
          <input v-model="form.password" type="password" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <span v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</span>
        </div>

        <!-- Remember me -->
        <div class="flex items-center mt-2">
          <input v-model="form.remember" type="checkbox" class="mr-2"/>
          <span class="text-sm text-gray-700">Se souvenir de moi</span>
        </div>

        <!-- Bouton Login -->
        <button type="submit" class="w-full mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition-transform transform hover:scale-105">
          Se connecter
        </button>
      </form>

      <!-- Liens -->
      <div class="mt-4 flex justify-between text-sm">
        <a href="/register-entreprise" class="text-blue-600 hover:underline">Créer une entreprise</a>
        <a href="/forgot-password" class="text-blue-600 hover:underline">Mot de passe oublié ?</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const form = useForm({
  company_name: '',
  email: '',
  password: '',
  remember: false,
})

const page = usePage()
const errorList = computed(() => {
  const errs = page.props?.errors || page.props?.value?.errors || {}
  return Object.values(errs)
})
const hasErrors = computed(() => errorList.value.length > 0)

function submit() {
  // Envoi des données vers la route Laravel correspondante
  form.post('/login', {
    onFinish: () => {
      // Réinitialise le formulaire si nécessaire
      form.reset('password')
    }
  })
}
</script>
