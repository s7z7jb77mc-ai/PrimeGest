<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post('/owner/login')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-900">
    <div class="bg-gray-800 p-8 rounded-2xl shadow-2xl w-full max-w-md">
      <div class="text-center mb-8">
        <div class="text-3xl font-bold">
          <span class="text-yellow-400">Prime</span><span class="text-white">Gest</span>
        </div>
        <div class="text-gray-400 text-sm mt-1">Interface propriétaire</div>
      </div>

      <div v-if="form.errors.email" class="bg-red-900/40 border border-red-500 text-red-300 px-4 py-3 rounded-lg mb-4 text-sm">
        {{ form.errors.email }}
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm text-gray-400 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full bg-gray-700 border border-gray-600 text-white px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-transparent outline-none"
            placeholder="votre@email.com"
            @keyup.enter="submit"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Mot de passe</label>
          <input
            v-model="form.password"
            type="password"
            class="w-full bg-gray-700 border border-gray-600 text-white px-4 py-2.5 rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-transparent outline-none"
            placeholder="••••••••"
            @keyup.enter="submit"
          />
        </div>
        <button
          @click="submit"
          :disabled="form.processing"
          class="w-full bg-yellow-500 hover:bg-yellow-400 text-black font-semibold py-2.5 rounded-lg transition-colors disabled:opacity-50"
        >
          {{ form.processing ? 'Connexion...' : 'Se connecter' }}
        </button>
      </div>
    </div>
  </div>
</template>
