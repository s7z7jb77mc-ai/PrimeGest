<template>
    <div class="relative min-h-screen flex items-center justify-center px-4">
        <div class="absolute inset-0">
            <img src="/images/background.jpg" alt="" class="w-full h-full object-cover"/>
            <div class="absolute inset-0 bg-black/60"></div>
        </div>

        <div class="relative z-10 w-full max-w-md p-8 rounded-2xl border border-white/30 bg-black/40 backdrop-blur-md shadow-xl text-white">
            <div class="text-center mb-6">
                <img src="/images/primegest.webp" alt="PrimeGest Logo" class="h-28 w-28 mx-auto mb-3"/>
                <h1 class="text-xl font-bold">Vérification de l'email</h1>
            </div>

            <p class="text-sm text-gray-300 text-center mb-6">
                Merci de vous être inscrit ! Avant de commencer, veuillez vérifier votre adresse e-mail
                en cliquant sur le lien que nous venons de vous envoyer. Si vous n'avez pas reçu l'e-mail,
                nous vous en enverrons un autre.
            </p>

            <div v-if="status === 'verification-link-sent'" class="mb-4 text-sm text-green-400 text-center">
                Un nouveau lien de vérification a été envoyé à votre adresse e-mail.
            </div>

            <form @submit.prevent="submit" class="mb-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-[#1A56A0] hover:bg-[#0B2D5E] text-white font-semibold py-2 rounded-lg transition"
                >
                    Renvoyer l'e-mail de vérification
                </button>
            </form>

            <form @submit.prevent="logout">
                <button
                    type="submit"
                    class="w-full text-sm text-gray-400 hover:text-white transition underline"
                >
                    Se déconnecter
                </button>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

defineProps<{ status?: string }>()

const form = useForm({})

function submit() {
    form.post('/email/verification-notification')
}

function logout() {
    useForm({}).post('/logout')
}
</script>
