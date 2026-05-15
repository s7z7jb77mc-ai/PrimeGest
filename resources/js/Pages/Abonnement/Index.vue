<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'

defineOptions({ layout: AppDashboardLayout })

const props = defineProps<{
    plan: string
    plan_expires_at: string | null
    jours_restants: number | null
    historique: Array<{
        id: number
        plan: string
        amount: number
        status: string
        payment_method: string | null
        payment_reference: string | null
        starts_at: string | null
        expires_at: string | null
        created_at: string
    }>
    prices: { premium: number; pro: number }
}>()

const form = ref({
    plan: 'premium' as 'premium' | 'pro',
    duree: 1,
    payment_method: '',
    payment_reference: '',
})

const submitting = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

const planLabel = computed(() => {
    const labels: Record<string, string> = { free: 'Free', premium: 'Premium', pro: 'Pro' }
    return labels[props.plan] ?? props.plan
})

const planColor = computed(() => {
    const colors: Record<string, string> = {
        free: 'bg-gray-100 text-gray-700',
        premium: 'bg-blue-100 text-blue-800',
        pro: 'bg-purple-100 text-purple-800',
    }
    return colors[props.plan] ?? 'bg-gray-100 text-gray-700'
})

const joursWarning = computed(() =>
    props.jours_restants !== null && props.jours_restants <= 7
)

const montantTotal = computed(() =>
    (props.prices[form.value.plan] ?? 0) * form.value.duree
)

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        pending: 'En attente',
        confirmed: 'Confirmé',
        expired: 'Expiré',
    }
    return labels[status] ?? status
}

const statusColor = (status: string) => {
    const colors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-green-100 text-green-800',
        expired: 'bg-red-100 text-red-800',
    }
    return colors[status] ?? 'bg-gray-100 text-gray-700'
}

function submit() {
    submitting.value = true
    successMsg.value = ''
    errorMsg.value = ''

    router.post('/abonnement/demande', form.value, {
        onSuccess: () => {
            successMsg.value = 'Demande envoyée. L\'équipe PrimeGest la confirmera sous 24h.'
            form.value.payment_method = ''
            form.value.payment_reference = ''
        },
        onError: (errors) => {
            errorMsg.value = Object.values(errors).join(' ')
        },
        onFinish: () => {
            submitting.value = false
        },
    })
}
</script>

<template>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-8">

        <!-- En-tête plan actuel -->
        <div class="bg-white rounded-2xl shadow p-6 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-sm text-gray-500 mb-1">Plan actuel</p>
                <div class="flex items-center gap-3">
                    <span :class="['px-3 py-1 rounded-full text-sm font-bold', planColor]">
                        {{ planLabel }}
                    </span>
                    <span v-if="plan !== 'free' && plan_expires_at" class="text-sm text-gray-600">
                        Valide jusqu'au
                        <strong>{{ new Date(plan_expires_at).toLocaleDateString('fr-FR') }}</strong>
                    </span>
                </div>

                <!-- Avertissement expiration proche -->
                <div v-if="joursWarning && plan !== 'free'"
                     class="mt-3 flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-sm text-yellow-800">
                    <span>⚠</span>
                    <span>Expire dans <strong>{{ jours_restants }} jour{{ jours_restants !== 1 ? 's' : '' }}</strong> — pensez à renouveler.</span>
                </div>
            </div>

            <div v-if="plan === 'free'" class="text-sm text-gray-400 italic">
                Passez à Premium ou Pro pour débloquer toutes les fonctionnalités.
            </div>
        </div>

        <!-- Formulaire de demande -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-5">Souscrire / Renouveler un abonnement</h2>

            <div v-if="successMsg" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
                {{ successMsg }}
            </div>
            <div v-if="errorMsg" class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                {{ errorMsg }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">

                <!-- Choix du plan -->
                <div class="grid grid-cols-2 gap-3">
                    <button type="button"
                            @click="form.plan = 'premium'"
                            :class="['border-2 rounded-xl p-4 text-left transition',
                                     form.plan === 'premium'
                                        ? 'border-blue-500 bg-blue-50'
                                        : 'border-gray-200 hover:border-blue-200']">
                        <p class="font-bold text-gray-800">Premium</p>
                        <p class="text-blue-600 font-bold text-lg">{{ prices.premium }} $/mois</p>
                        <p class="text-xs text-gray-500 mt-1">Illimité · Exports PDF · Créances</p>
                    </button>
                    <button type="button"
                            @click="form.plan = 'pro'"
                            :class="['border-2 rounded-xl p-4 text-left transition',
                                     form.plan === 'pro'
                                        ? 'border-purple-500 bg-purple-50'
                                        : 'border-gray-200 hover:border-purple-200']">
                        <p class="font-bold text-gray-800">Pro</p>
                        <p class="text-purple-600 font-bold text-lg">{{ prices.pro }} $/mois</p>
                        <p class="text-xs text-gray-500 mt-1">Tout Premium · Succursales illimitées</p>
                    </button>
                </div>

                <!-- Durée -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Durée
                    </label>
                    <select v-model="form.duree"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]">
                        <option v-for="n in 12" :key="n" :value="n">
                            {{ n }} mois — {{ (prices[form.plan] ?? 0) * n }} $
                        </option>
                    </select>
                </div>

                <!-- Méthode de paiement -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Méthode de paiement
                    </label>
                    <input v-model="form.payment_method"
                           type="text"
                           placeholder="Ex : MTN MoMo, Airtel Money, virement..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]"
                           required />
                </div>

                <!-- Référence de paiement -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Référence / Numéro de transaction
                    </label>
                    <input v-model="form.payment_reference"
                           type="text"
                           placeholder="Ex : TXN-1234567890"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]"
                           required />
                </div>

                <!-- Récap montant -->
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700">
                    Montant total :
                    <strong class="text-gray-900">{{ montantTotal }} $</strong>
                    pour {{ form.duree }} mois de {{ form.plan === 'premium' ? 'Premium' : 'Pro' }}
                </div>

                <button type="submit"
                        :disabled="submitting"
                        class="w-full bg-[#1A56A0] hover:bg-[#0B2D5E] disabled:opacity-60 text-white font-semibold py-3 rounded-xl transition text-sm">
                    {{ submitting ? 'Envoi en cours...' : 'Soumettre ma demande' }}
                </button>
            </form>
        </div>

        <!-- Historique -->
        <div v-if="historique.length" class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Historique des abonnements</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="pb-2 pr-4">Plan</th>
                            <th class="pb-2 pr-4">Montant</th>
                            <th class="pb-2 pr-4">Statut</th>
                            <th class="pb-2 pr-4">Début</th>
                            <th class="pb-2">Fin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in historique" :key="s.id" class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium capitalize">{{ s.plan }}</td>
                            <td class="py-2 pr-4">{{ s.amount }} $</td>
                            <td class="py-2 pr-4">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusColor(s.status)]">
                                    {{ statusLabel(s.status) }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-gray-500">{{ s.starts_at ?? '—' }}</td>
                            <td class="py-2 text-gray-500">{{ s.expires_at ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>
