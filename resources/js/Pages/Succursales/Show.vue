<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  succursale: { type: Object, default: null },
})

const go = (path) => router.get(path)
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-4">
      <div>
        <h1 class="text-2xl font-bold">Succursale: {{ props.succursale?.nom }}</h1>
        <div class="text-sm text-gray-600">{{ props.succursale?.adresse || '-' }}</div>
      </div>
      <button type="button" @click="go('/succursales')" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
        Retour
      </button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <button @click="go('/dashboard')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Dashboard</button>
      <button @click="go('/ressources-humaines')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">RH</button>
      <button @click="go('/caisse')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Caisse</button>
      <button @click="go('/mouvement-stocks')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Mouvement stock</button>
      <button @click="go('/produits')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Produits</button>
      <button @click="go('/tiers')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Clients/Fournisseurs</button>
      <button @click="go('/creances-dettes')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Créances & dettes</button>
      <button @click="go('/journals')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Journal</button>
      <button @click="go('/rapport')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Rapport</button>
      <button @click="go('/archives')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Archives</button>
      <button @click="go('/users')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Utilisateurs</button>
      <button @click="go('/transferts')" class="bg-white shadow rounded p-4 text-left hover:shadow-md">Transfert</button>
    </div>
  </div>
</template>
