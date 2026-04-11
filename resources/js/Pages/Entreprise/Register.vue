<template>
  <div class="relative min-h-screen flex flex-col">
    <!-- Arrière-plan principal -->
    <div class="absolute inset-0">
      <img src="/build/assets/arriere_plan.jpg"
           alt="arrière plan"
           class="w-full h-full object-cover"/>
      <div class="absolute inset-0 bg-black/50"></div>
    </div>


    <!-- En-tête fixe -->
    <header class="fixed top-0 left-0 w-full z-20 bg-BLACK backdrop-blur-md border-b border-black shadow-md p-4 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <img src="/build/assets/primegest.png" alt="PrimeGest Logo" class="h-16 w-16 object-contain"/>
        <div>
          <h1 class="text-xl font-bold text-blue-30">PrimeGest</h1>
          <p class="text-sm text-blue-30">Votre outil idéal pour la gestion de vos entreprises</p>
        </div>
      </div>
      <!-- Lien vers la page de login (URL statique) -->
      <a href="/login" class="btn bg-blue-600 hover:bg-blue-700 text-white">Connexion</a>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4 pt-32">
      <!-- Formulaire transparent -->
      <div class="w-full max-w-xl p-8 rounded-2xl border border-black backdrop-blur-md shadow-lg">
        <h2 class="text-2xl font-bold text-center mb-6 text-white">Créer une entreprise</h2>

        <form @submit.prevent="submit" class="space-y-4 text-white">
          <!-- Infos Entreprise -->
          <div>
            <label class="label text-white">Nom Entreprise</label>
            <input v-model="form.entreprise_name" type="text" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
            <span v-if="form.errors.entreprise_name" class="text-sm text-red-300">{{ form.errors.entreprise_name }}</span>
          </div>

          <div>
            <label class="label text-white">Email Entreprise</label>
            <input v-model="form.entreprise_email" type="email" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
            <span v-if="form.errors.entreprise_email" class="text-sm text-red-300">{{ form.errors.entreprise_email }}</span>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label text-white">Téléphone</label>
              <input v-model="form.entreprise_phone" type="text" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
            </div>
            <div>
              <label class="label text-white">Adresse</label>
              <input v-model="form.entreprise_address" type="text" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
            </div>
          </div>

          <!-- Admin -->
          <h3 class="text-lg font-semibold mt-6 text-white">Compte Super Admin</h3>

          <div>
            <label class="label text-white">Nom complet</label>
            <input v-model="form.admin_name" type="text" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
          </div>

          <div>
            <label class="label text-white">Email</label>
            <input v-model="form.admin_email" type="email" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label text-white">Mot de passe</label>
              <input v-model="form.admin_password" type="password" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
            </div>
            <div>
              <label class="label text-white">Confirmer</label>
              <input v-model="form.admin_password_confirmation" type="password" class="input bg-white/20 text-white placeholder-white/70 border border-black"/>
            </div>
          </div>

          <button type="submit" class="btn w-full mt-4 bg-blue-600 hover:bg-blue-700 text-white">Créer l’entreprise</button>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  entreprise_name: '',
  entreprise_email: '',
  entreprise_phone: '',
  entreprise_address: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
  admin_password_confirmation: ''
})


function submit() {
  form.post('/register-entreprise')

}
</script>
