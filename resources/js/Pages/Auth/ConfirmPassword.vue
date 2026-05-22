<template>
    <div class="relative min-h-screen flex items-center justify-center px-4">
        <div class="absolute inset-0">
            <img src="/images/background.jpg" alt="" class="w-full h-full object-cover"/>
            <div class="absolute inset-0 bg-black/60"></div>
        </div>

        <div class="relative z-10 w-full max-w-md p-8 rounded-2xl border border-white/30 bg-black/40 backdrop-blur-md shadow-xl text-white">
            <div class="text-center mb-6">
                <img src="/images/primegest.webp" alt="PrimeGest Logo" class="h-28 w-28 mx-auto mb-3"/>
                <h1 class="text-xl font-bold">Confirmation du mot de passe</h1>
            </div>

            <p class="text-sm text-gray-300 text-center mb-6">
                Il s'agit d'une zone sécurisée de l'application. Veuillez confirmer votre mot de passe
                avant de continuer.
            </p>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-1">Mot de passe</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full bg-white/10 border border-white/30 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1A56A0]"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-[#1A56A0] hover:bg-[#0B2D5E] text-white font-semibold py-2 rounded-lg transition"
                >
                    Confirmer
                </button>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const form = useForm({ password: '' })

function submit() {
    form.post('/confirm-password', {
        onFinish: () => form.reset('password'),
    })
}
</script>
