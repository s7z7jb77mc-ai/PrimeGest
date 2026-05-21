<template>
  <div class="relative min-h-screen flex flex-col">

    <!-- Arrière-plan -->
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

    <!-- En-tête fixe -->
    <header class="fixed top-0 left-0 w-full z-20 bg-black/50 backdrop-blur-md border-b border-white/20 shadow-md p-4 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <img src="/images/primegest.webp" alt="PrimeGest Logo" class="h-16 w-16 object-contain">
        <div>
          <h1 class="text-xl font-bold text-white">PrimeGest</h1>
          <p class="text-sm text-gray-300">
            {{ currentLang === 'fr' ? 'Votre outil idéal pour la gestion de vos entreprises' : 'Your ideal business management tool' }}
          </p>
        </div>
      </div>
      <a href="/login" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-medium transition">
        {{ t('login') }}
      </a>
    </header>

    <!-- Contenu principal -->
    <main class="relative z-10 flex-grow flex items-center justify-center px-4 pt-32 pb-12">
      <div class="w-full max-w-xl p-8 rounded-2xl border border-white/30 bg-black/40 backdrop-blur-md shadow-xl">

        <h2 class="text-2xl font-bold text-center mb-6 text-white">
          {{ t('register_submit') }}
        </h2>

        <!-- Succès -->
        <div v-if="successMsg" class="mb-4 bg-green-500/20 border border-green-400/50 text-green-200 rounded-lg p-3 text-sm">
          {{ successMsg }}
        </div>

        <!-- Erreurs globales -->
        <div v-if="hasErrors" class="mb-4 bg-red-500/20 border border-red-400/50 text-red-200 rounded-lg p-3 text-sm space-y-1">
          <div v-for="(msg, key) in errorList" :key="key">{{ msg }}</div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">

          <!-- Infos Entreprise -->
          <fieldset class="space-y-3">
            <legend class="text-white font-semibold text-sm uppercase tracking-wider mb-2 border-b border-white/20 pb-1 w-full">
              {{ t('register_company_section') }}
            </legend>

            <div>
              <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_company_name') }}</label>
              <input
                v-model="form.entreprise_name"
                type="text"
                :placeholder="currentLang === 'fr' ? 'Ex: Ma Société SARL' : 'Ex: My Company LLC'"
                class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
              />
              <span v-if="form.errors.entreprise_name" class="text-red-300 text-xs mt-1 block">
                {{ form.errors.entreprise_name }}
              </span>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_company_email') }}</label>
              <input
                v-model="form.entreprise_email"
                type="email"
                placeholder="contact@masociete.com"
                class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent"
              />
              <span v-if="form.errors.entreprise_email" class="text-red-300 text-xs mt-1 block">
                {{ form.errors.entreprise_email }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_company_phone') }}</label>
                <input
                  v-model="form.entreprise_phone"
                  type="text"
                  placeholder="+243 999 999 999"
                  class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_company_address') }}</label>
                <input
                  v-model="form.entreprise_address"
                  type="text"
                  :placeholder="currentLang === 'fr' ? 'Kinshasa, RDC' : 'New York, USA'"
                  class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                />
              </div>
            </div>
          </fieldset>

          <!-- Compte Super Admin -->
          <fieldset class="space-y-3 mt-2">
            <legend class="text-white font-semibold text-sm uppercase tracking-wider mb-2 border-b border-white/20 pb-1 w-full">
              {{ t('register_admin_section') }}
            </legend>

            <div>
              <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_admin_name') }}</label>
              <input
                v-model="form.admin_name"
                type="text"
                :placeholder="currentLang === 'fr' ? 'Jean Dupont' : 'John Doe'"
                class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
              />
              <span v-if="form.errors.admin_name" class="text-red-300 text-xs mt-1 block">
                {{ form.errors.admin_name }}
              </span>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_admin_email') }}</label>
              <input
                v-model="form.admin_email"
                type="email"
                placeholder="admin@masociete.com"
                class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
              />
              <span v-if="form.errors.admin_email" class="text-red-300 text-xs mt-1 block">
                {{ form.errors.admin_email }}
              </span>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_admin_password') }}</label>
                <input
                  v-model="form.admin_password"
                  type="password"
                  :placeholder="currentLang === 'fr' ? 'Min. 6 caractères' : 'Min. 6 characters'"
                  class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                />
                <span v-if="form.errors.admin_password" class="text-red-300 text-xs mt-1 block">
                  {{ form.errors.admin_password }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-200 mb-1">{{ t('register_admin_confirm') }}</label>
                <input
                  v-model="form.admin_password_confirmation"
                  type="password"
                  :placeholder="currentLang === 'fr' ? 'Répéter le mot de passe' : 'Repeat password'"
                  class="w-full bg-white/10 border border-white/30 text-white placeholder-white/50 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
                />
              </div>
            </div>
          </fieldset>

          <button
            type="submit"
            :disabled="form.processing"
            class="w-full mt-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 px-4 rounded-lg transition-transform transform hover:scale-105"
          >
            <span v-if="form.processing">{{ t('register_submitting') }}</span>
            <span v-else>{{ t('register_submit') }}</span>
          </button>
        </form>

        <p class="text-center text-gray-400 text-sm mt-4">
          {{ t('register_already') }}
          <a href="/login" class="text-blue-400 hover:underline ml-1">{{ t('register_signin') }}</a>
        </p>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
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
  entreprise_name: '',
  entreprise_email: '',
  entreprise_phone: '',
  entreprise_address: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
  admin_password_confirmation: '',
})

const successMsg = ref('')
const page = usePage()

const errorList = computed(() => Object.values((page.props as any)?.errors || {}) as string[])
const hasErrors  = computed(() => errorList.value.length > 0)

function submit() {
  successMsg.value = ''
  form.post('/register-entreprise', {
    onSuccess: () => {
      successMsg.value = t('register_success')
      form.reset()
    },
  })
}
</script>