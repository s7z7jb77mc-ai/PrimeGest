<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center px-6 bg-cover bg-center bg-no-repeat relative"
    style="background-image: url('/build/assets/background.jpg');"
  >
    <!-- Overlay sombre pour lisibilité -->
    <div class="absolute inset-0 bg-black/50 pointer-events-none"></div>

    <!-- Sélecteur de langue (coin haut droit) -->
    <div class="absolute top-4 right-4 flex items-center gap-2 z-20">
      <button
        @click="switchLang('fr')"
        :class="currentLang === 'fr' ? 'bg-yellow-400 text-black' : 'bg-white/10 text-gray-300 border border-white/30'"
        class="px-3 py-1 rounded text-sm font-medium transition"
      >FR</button>
      <button
        @click="switchLang('en')"
        :class="currentLang === 'en' ? 'bg-yellow-400 text-black' : 'bg-white/10 text-gray-300 border border-white/30'"
        class="px-3 py-1 rounded text-sm font-medium transition"
      >EN</button>
    </div>

    <!-- Contenu -->
    <div class="relative z-10 flex flex-col items-center">

      <!-- Logo -->
      <div class="mb-6 animate-fade-in">
        <img
          src="/build/assets/primegest.png"
          alt="Logo PrimeGest"
          class="w-40 h-40 object-contain mx-auto drop-shadow-md"
        />
      </div>

      <!-- Titre -->
      <div class="text-center mb-10 text-white">
        <h1 class="text-4xl md:text-5xl font-extrabold mb-4 drop-shadow-lg">
          {{ t('welcome_title') }} <span class="text-yellow-300">PrimeGest</span>
        </h1>
        <p class="text-lg md:text-xl max-w-2xl mx-auto drop-shadow-md">
          {{ t('welcome_subtitle') }}
        </p>
      </div>

      <!-- Boutons -->
      <div class="flex space-x-6 mb-12">
        <button
          @click="$inertia.visit('/register-entreprise')"
          class="bg-yellow-400 text-black px-6 py-3 rounded-2xl text-lg font-semibold shadow-md hover:bg-yellow-500 transition-transform transform hover:scale-105"
        >
          {{ t('welcome_register') }}
        </button>
        <button
          @click="$inertia.visit('/login')"
          class="bg-white text-yellow-500 px-6 py-3 rounded-2xl text-lg font-semibold shadow-md hover:bg-yellow-50 transition-transform transform hover:scale-105"
        >
          {{ t('welcome_login') }}
        </button>
      </div>

      <!-- Texte secondaire -->
      <div class="text-center text-white text-base md:text-lg max-w-xl drop-shadow-md">
        {{ t('welcome_secondary') }}
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t as _t, getStoredLang, setLang } from '@/lang'

const currentLang = ref(getStoredLang())

function switchLang(lang: 'fr' | 'en') {
  currentLang.value = lang
  setLang(lang)
}

function t(key: string): string {
  void currentLang.value // force réactivité
  return _t(key)
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 1s ease-in-out;
}
</style>