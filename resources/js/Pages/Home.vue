<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center px-6 bg-cover bg-center bg-no-repeat"
    style="background-image: url('/build/assets/background.jpg');"
  >
    <!-- Logo -->
    <div class="mb-6 animate-fade-in">
      <img
        src="/build/assets/primegest.png"
        alt="Logo PrimeGest"
        class="w-40 h-40 object-contain mx-auto drop-shadow-md"
      />
    </div>

    <!-- Titre et texte principal -->
    <div class="text-center mb-10 text-white">
      <h1 class="text-4xl md:text-5xl font-extrabold mb-4 drop-shadow-lg">
        Bienvenue sur <span class="text-yellow-300">PrimeGest</span>
      </h1>
      <p class="text-lg md:text-xl max-w-2xl mx-auto drop-shadow-md">
        Un outil numérique moderne et performant conçu pour
        <span class="font-semibold">simplifier la gestion</span> de votre entreprise,
        améliorer votre productivité et centraliser vos activités.
      </p>
    </div>

    <!-- Boutons -->
    <div class="flex space-x-6 mb-12">
      <button
        @click="$inertia.visit('/register-entreprise')"
        class="bg-yellow-400 text-white px-6 py-3 rounded-2xl text-lg font-semibold shadow-md hover:bg-yellow-500 transition-transform transform hover:scale-105"
      >
        Inscription
      </button>
      <button
        @click="$inertia.visit('/login')"
        class="bg-white text-yellow-400 px-6 py-3 rounded-2xl text-lg font-semibold shadow-md hover:bg-yellow-50 transition-transform transform hover:scale-105"
      >
        Connexion
      </button>
    </div>

    <!-- Texte secondaire -->
    <div class="text-center text-white text-base md:text-lg max-w-xl drop-shadow-md">
      PrimeGest vous accompagne dans la transformation numérique de vos activités,
      en vous offrant des outils simples, intuitifs et fiables pour une gestion moderne.
    </div>
  </div>
</template>

<script setup>
// Aucun script particulier
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 1s ease-in-out;
}
</style>
