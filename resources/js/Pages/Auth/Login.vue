<template>
  <div class="relative min-h-screen flex items-center justify-center px-4">

    <!-- Arrière-plan -->
    <div class="absolute inset-0">
      <img src="/images/background.jpg" alt="arrière plan" class="w-full h-full object-cover"/>
      <div class="absolute inset-0 bg-black/60"></div>
    </div>

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
        <img src="/images/primegest.webp" alt="PrimeGest Logo" class="h-32 w-32 mx-auto mb-3"/>
        <h1 class="text-2xl font-bold text-white">
          {{ t('login') }} — PrimeGest
        </h1>
        <p class="text-sm text-gray-300 mt-1">
          {{ currentLang === 'fr' ? 'Connectez-vous pour gérer votre entreprise' : 'Sign in to manage your business' }}
        </p>
      </div>

      <!-- Erreurs globales -->
      <div v-if="hasErrors" class="mb-4 bg-red-500/20 border border-red-400/50 text-red-200 rounded-lg p-3 text-sm space-y-1">
        <div v-for="(msg, key) in translatedErrors" :key="key">{{ msg }}</div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">

        <!-- Nom entreprise -->
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">
            {{ t('company_name') }}
          </label>
          <input
            v-model="form.company_name"
            type="text"
            autocomplete="organization"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
          <span v-if="form.errors.company_name" class="text-red-300 text-xs mt-1 block">
            {{ translateError(form.errors.company_name) }}
          </span>
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">
            {{ t('email') }}
          </label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
          <span v-if="form.errors.email" class="text-red-300 text-xs mt-1 block">
            {{ translateError(form.errors.email) }}
          </span>
        </div>

        <!-- Mot de passe -->
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">
            {{ t('password') }}
          </label>
          <input
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
          <span v-if="form.errors.password" class="text-red-300 text-xs mt-1 block">
            {{ translateError(form.errors.password) }}
          </span>
        </div>

        <!-- Remember me -->
        <div class="flex items-center mt-2">
          <input v-model="form.remember" type="checkbox" class="mr-2 accent-blue-500"/>
          <span class="text-sm text-gray-300">{{ t('remember_me') }}</span>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full mt-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 px-4 rounded-lg transition-transform transform hover:scale-105"
        >
          <span v-if="form.processing">
            {{ currentLang === 'fr' ? 'Connexion...' : 'Signing in...' }}
          </span>
          <span v-else>{{ t('login') }}</span>
        </button>
      </form>

      <!-- Liens -->
      <div class="mt-4 flex justify-between text-sm">
        <a href="/register-entreprise" class="text-blue-400 hover:underline">
          {{ currentLang === 'fr' ? 'Créer une entreprise' : 'Create a company' }}
        </a>
        <a href="/forgot-password" class="text-blue-400 hover:underline">
          {{ t('forgot_password') }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { t as _t, getStoredLang, setLang } from '@/lang'

const currentLang = ref(getStoredLang())

function switchLang(lang: 'fr' | 'en') {
  currentLang.value = lang
  setLang(lang)
}

function t(key: string): string {
  void currentLang.value
  return _t(key)
}

const form = useForm({
  company_name: '',
  email: '',
  password: '',
  remember: false,
})

const page = usePage()

const errorList = computed(() => {
  const errs = (page.props as any)?.errors || {}
  return Object.values(errs) as string[]
})
const hasErrors = computed(() => errorList.value.length > 0)

const errorTranslationMap: Record<string, string> = {
  'These credentials do not match our records.': 'error_invalid_credentials',
  'Ces identifiants ne correspondent pas à nos enregistrements.': 'error_invalid_credentials',
  'Email non reconnu ou mot de passe incorrect.': 'error_invalid_credentials',
  'Email ou mot de passe incorrect.': 'error_invalid_credentials',
  'The password is incorrect.': 'error_password_incorrect',
  'Mot de passe incorrect.': 'error_password_incorrect',
  'Mot de passe Super Admin incorrect.': 'error_password_incorrect',
  "Cette entreprise n'existe pas.": 'error_company_not_found',
  'This company does not exist.': 'error_company_not_found',
  "Accès réservé au Super Admin.": 'error_access_restricted',
  "Accès réservé au Super Admin ou au manager de la succursale.": 'error_access_restricted',
  "Accès non autorisé pour cette page.": 'error_access_denied',
  'Quantité en stock insuffisante pour ce produit.': 'error_insufficient_stock',
  'Stock insuffisant dans la succursale source.': 'error_insufficient_stock',
}

function translateError(msg: string): string {
  const key = errorTranslationMap[msg]
  return key ? t(key) : msg
}

const translatedErrors = computed(() => errorList.value.map(msg => translateError(msg)))

async function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
    onSuccess: async () => {
      // Obtenir un token Sanctum pour la sync offline
      try {
        const deviceId = localStorage.getItem('primegest_device_id') ?? crypto.randomUUID()
        localStorage.setItem('primegest_device_id', deviceId)

        const res = await fetch('/api/auth/token', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            company_name: form.company_name,
            email:        form.email,
            password:     form.password,
            device_id:    deviceId,
          }),
        })
        if (res.ok) {
          const data = await res.json()
          localStorage.setItem('api_token', data.token)
        }
      } catch (e) {
        console.warn('[Auth] Token Sanctum non obtenu:', e)
      }
    }
  })
}
</script>