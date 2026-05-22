<template>
  <div class="relative min-h-screen flex items-center justify-center px-4">

    <!-- Arrière-plan sombre -->
    <div
      class="absolute inset-0 z-0"
      style="background-image: url('/images/background.jpg');
            background-size: cover;
            background-position: center;"
    ></div>


    <!-- Sélecteur de langue -->
    <div class="absolute top-4 right-4 flex items-center gap-2 z-20">
      <button
        @click="switchLang('fr')"
        :class="currentLang === 'fr' ? 'bg-blue-600 text-white' : 'bg-white/10 text-gray-300 border border-white/30'"
        class="px-3 py-1 rounded text-sm font-medium transition"
      >FR</button>
      <button
        @click="switchLang('en')"
        :class="currentLang === 'en' ? 'bg-blue-600 text-white' : 'bg-white/10 text-gray-300 border border-white/30'"
        class="px-3 py-1 rounded text-sm font-medium transition"
      >EN</button>
    </div>

    <!-- Formulaire -->
    <div class="relative z-10 w-full max-w-md p-8 rounded-2xl border border-white/30 bg-black/40 backdrop-blur-md shadow-xl">

      <div class="text-center mb-6">
        <img src="/images/primegest.webp" alt="PrimeGest Logo" class="h-28 w-28 mx-auto mb-3"/>
        <h1 class="text-2xl font-bold text-white">
          {{ currentLang === 'fr' ? 'Nouveau mot de passe' : 'New password' }}
        </h1>
        <p class="text-sm text-gray-300 mt-1">
          {{ currentLang === 'fr' ? 'Lien valable 30 minutes' : 'Link valid for 30 minutes' }}
        </p>
      </div>

      <!-- Erreurs -->
      <div v-if="hasErrors" class="mb-4 bg-red-500/20 border border-red-400/50 text-red-200 rounded-lg p-3 text-sm space-y-1">
        <div v-for="(msg, key) in errorList" :key="key">{{ msg }}</div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <input type="hidden" v-model="form.token"/>
        <input type="hidden" v-model="form.email"/>

        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">
            {{ currentLang === 'fr' ? 'Entreprise' : 'Company' }}
          </label>
          <input
            v-model="form.company_name"
            type="text"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
          <span v-if="form.errors.company_name" class="text-red-300 text-xs mt-1 block">
            {{ form.errors.company_name }}
          </span>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">
            {{ currentLang === 'fr' ? 'Nouveau mot de passe' : 'New password' }}
          </label>
          <input
            v-model="form.password"
            type="password"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
          <span v-if="form.errors.password" class="text-red-300 text-xs mt-1 block">
            {{ form.errors.password }}
          </span>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">
            {{ currentLang === 'fr' ? 'Confirmer le mot de passe' : 'Confirm password' }}
          </label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full mt-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 px-4 rounded-lg transition-transform transform hover:scale-105"
        >
          <span v-if="form.processing">
            {{ currentLang === 'fr' ? 'Réinitialisation...' : 'Resetting...' }}
          </span>
          <span v-else>
            {{ currentLang === 'fr' ? 'Réinitialiser' : 'Reset' }}
          </span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { getStoredLang, setLang } from '@/lang'

const props = defineProps<{
  email?: string
  token?: string
  company_name?: string
}>()

// ✅ Lit la langue stockée dès le montage
const currentLang = ref(getStoredLang())

function switchLang(lang: 'fr' | 'en') {
  currentLang.value = lang
  setLang(lang)
}

const form = useForm({
  token: props.token || '',
  email: props.email || '',
  company_name: props.company_name || '',
  password: '',
  password_confirmation: '',
})

const page = usePage()
const errorList = computed(() => Object.values((page.props as any)?.errors || {}) as string[])
const hasErrors = computed(() => errorList.value.length > 0)

function submit() { form.post('/reset-password') }
</script>