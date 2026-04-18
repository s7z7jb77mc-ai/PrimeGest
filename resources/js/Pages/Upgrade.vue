<template>
    <AppLayout>
        <div class="max-w-5xl mx-auto py-12 px-4">
            <h1 class="text-2xl font-medium text-center mb-2">Choisissez votre plan</h1>
            <p class="text-center text-gray-500 mb-10">
                Commencez gratuitement, évoluez quand vous êtes prêt
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Free -->
                <div class="border rounded-xl p-6" :class="plan === 'free' ? 'border-blue-500 border-2' : 'border-gray-200'">
                    <div class="text-sm text-gray-500 mb-1">Free</div>
                    <div class="text-3xl font-medium mb-1">0 <span class="text-base font-normal text-gray-400">$/mois</span></div>
                    <div class="text-xs text-gray-400 mb-6">Pour démarrer</div>
                    <ul class="space-y-2 text-sm text-gray-600 mb-8">
                        <li>3 utilisateurs</li>
                        <li>50 produits</li>
                        <li>20 clients / fournisseurs</li>
                        <li>250 MB stockage</li>
                        <li class="text-gray-300">Gestion des dettes</li>
                        <li class="text-gray-300">Réductions</li>
                        <li class="text-gray-300">Exports PDF / Excel</li>
                        <li class="text-gray-300">Succursales</li>
                    </ul>
                    <div v-if="plan === 'free'" class="text-center text-sm text-blue-500 font-medium">Plan actuel</div>
                </div>

                <!-- Premium -->
                <div class="border-2 border-blue-500 rounded-xl p-6 relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-500 text-white text-xs px-3 py-1 rounded-full">
                        Recommandé
                    </div>
                    <div class="text-sm text-gray-500 mb-1">Premium</div>
                    <div class="text-3xl font-medium mb-1">7 <span class="text-base font-normal text-gray-400">$/mois</span></div>
                    <div class="text-xs text-gray-400 mb-6">Pour les boutiques en croissance</div>
                    <ul class="space-y-2 text-sm text-gray-600 mb-8">
                        <li>Utilisateurs illimités</li>
                        <li>Produits illimités</li>
                        <li>Clients / fournisseurs illimités</li>
                        <li>Stockage illimité</li>
                        <li>Gestion des dettes</li>
                        <li>Réductions</li>
                        <li>Exports PDF / Excel</li>
                        <li class="text-gray-300">Succursales</li>
                    </ul>
                    <div v-if="plan === 'premium'" class="text-center text-sm text-blue-500 font-medium">Plan actuel</div>
                    <button v-else @click="contacter('premium')"
                        class="w-full bg-blue-500 text-white py-2 rounded-lg text-sm hover:bg-blue-600 transition">
                        Passer au Premium
                    </button>
                </div>

                <!-- Pro -->
                <div class="border rounded-xl p-6" :class="plan === 'pro' ? 'border-blue-500 border-2' : 'border-gray-200'">
                    <div class="text-sm text-gray-500 mb-1">Pro</div>
                    <div class="text-3xl font-medium mb-1">10 <span class="text-base font-normal text-gray-400">$/mois</span></div>
                    <div class="text-xs text-gray-400 mb-6">Pour les entreprises multi-sites</div>
                    <ul class="space-y-2 text-sm text-gray-600 mb-8">
                        <li>Tout Premium +</li>
                        <li>Succursales illimitées</li>
                        <li>Transferts inter-dépôts</li>
                        <li>Dashboard agrégé</li>
                    </ul>
                    <div v-if="plan === 'pro'" class="text-center text-sm text-blue-500 font-medium">Plan actuel</div>
                    <button v-else @click="contacter('pro')"
                        class="w-full border border-blue-500 text-blue-500 py-2 rounded-lg text-sm hover:bg-blue-50 transition">
                        Passer au Pro
                    </button>
                </div>
            </div>

            <!-- Instructions paiement -->
            <div class="mt-12 bg-gray-50 rounded-xl p-6 text-sm text-gray-600 max-w-lg mx-auto text-center">
                <p class="font-medium text-gray-800 mb-2">Comment upgrader ?</p>
                <p>Envoyez un paiement Mobile Money ou virement, puis contactez-nous avec votre référence de paiement. L'activation est manuelle sous 24h.</p>
                <a href="mailto:support@primegest.app" class="inline-block mt-4 text-blue-500 hover:underline">
                    support@primegest.app
                </a>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const plan = computed(() => page.props.plan ?? 'free')

const contacter = (targetPlan) => {
    const subject = `Upgrade PrimeGest ${targetPlan}`
    const body = `Bonjour, je souhaite passer au plan ${targetPlan}. Voici ma référence de paiement : `
    window.location.href = `mailto:support@primegest.app?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
}
</script>
