<template>
  <div class="relative min-h-screen flex items-center justify-center px-4">

    <!-- Arrière-plan sombre (même que Register) -->
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
        <img src="/images/primegest.webp" alt="PrimeGest Logo" class="h-20 w-20 mx-auto mb-3"/>
        <h1 class="text-2xl font-bold text-white">{{ t('forgot_password') }}</h1>
        <p class="text-sm text-gray-300 mt-1">
          {{ currentLang === 'fr' ? 'Lien réservé aux super admins' : 'Link reserved for super admins' }}
        </p>
      </div>

      <!-- Succès -->
      <div v-if="props.status" class="mb-4 bg-green-500/20 border border-green-400/50 text-green-200 rounded-lg p-3 text-sm">
        {{ props.status }}
      </div>

      <!-- Erreurs -->
      <div v-if="hasErrors" class="mb-4 bg-red-500/20 border border-red-400/50 text-red-200 rounded-lg p-3 text-sm space-y-1">
        <div v-for="(msg, key) in errorList" :key="key">{{ msg }}</div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
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
          <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('email') }}</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
          />
          <span v-if="form.errors.email" class="text-red-300 text-xs mt-1 block">
            {{ form.errors.email }}
          </span>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full mt-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 px-4 rounded-lg transition-transform transform hover:scale-105"
        >
          <span v-if="form.processing">
            {{ currentLang === 'fr' ? 'Envoi en cours...' : 'Sending...' }}
          </span>
          <span v-else>
            {{ currentLang === 'fr' ? 'Réinitialiser le mot de passe' : 'Reset password' }}
          </span>
        </button>
      </form>

      <div class="mt-4 text-sm text-center">
        <a href="/login" class="text-blue-400 hover:underline">
          {{ currentLang === 'fr' ? 'Retour à la connexion' : 'Back to login' }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { t as _t, getStoredLang, setLang } from '@/lang'

const props = defineProps<{ status?: string }>()

// ✅ Lit la langue stockée dès le montage — cohérence inter-pages
const currentLang = ref(getStoredLang())

function switchLang(lang: 'fr' | 'en') {
  currentLang.value = lang
  setLang(lang)
}

function t(key: string): string {
  void currentLang.value
  return _t(key)
}

const form = useForm({ company_name: '', email: '' })

const page = usePage()
const errorList = computed(() => Object.values((page.props as any)?.errors || {}) as string[])
const hasErrors = computed(() => errorList.value.length > 0)

function submit() { form.post('/forgot-password') }
</script>