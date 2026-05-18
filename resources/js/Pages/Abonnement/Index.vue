<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import axios from 'axios'
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
    promo_prices: { premium?: Record<number, number>; pro?: Record<number, number> }
    usd_to_cdf_rate: number
}>()

// ── État formulaire ──────────────────────────────────────────────────────────

const form = ref({
    plan:   'premium' as 'premium' | 'pro',
    duree:  1,
    phone:  '',
    devise: 'USD' as 'USD' | 'CDF',
})

// ── États UI ─────────────────────────────────────────────────────────────────

type Etape = 'formulaire' | 'attente' | 'succes' | 'timeout' | 'erreur'

const etape      = ref<Etape>('formulaire')
const erreurMsg  = ref('')
const reference  = ref('')
let   pollingId: ReturnType<typeof setInterval> | null = null
let   tentatives = 0

// ── Calcul du montant ────────────────────────────────────────────────────────

const montantUsd = computed((): number => {
    const promo = props.promo_prices[form.value.plan]
    if (promo && promo[form.value.duree] !== undefined) {
        return promo[form.value.duree]
    }
    return (props.prices[form.value.plan] ?? 0) * form.value.duree
})

const montantAffiche = computed((): string => {
    if (form.value.devise === 'CDF') {
        const cdf = Math.round(montantUsd.value * props.usd_to_cdf_rate)
        return `${cdf.toLocaleString('fr-FR')} CDF`
    }
    return `${montantUsd.value} $`
})

const aReduction = computed((): boolean => {
    const promo = props.promo_prices[form.value.plan]
    return !!(promo && promo[form.value.duree] !== undefined)
})

const montantSansReduction = computed((): string => {
    const base = (props.prices[form.value.plan] ?? 0) * form.value.duree
    if (form.value.devise === 'CDF') {
        return `${Math.round(base * props.usd_to_cdf_rate).toLocaleString('fr-FR')} CDF`
    }
    return `${base} $`
})

// ── Labels ───────────────────────────────────────────────────────────────────

const planLabel = computed(() => ({ free: 'Free', premium: 'Premium', pro: 'Pro' })[props.plan] ?? props.plan)

const planColor = 'bg-gray-100 text-gray-700'

const joursWarning = computed(() => props.jours_restants !== null && props.jours_restants <= 7)

function statusLabel(status: string): string {
    return ({ pending: 'En attente', confirmed: 'Confirmé', expired: 'Expiré', failed: 'Échoué' })[status] ?? status
}

function statusColor(status: string): string {
    return ({
        pending:   'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-green-100 text-green-800',
        expired:   'bg-red-100 text-red-800',
        failed:    'bg-red-100 text-red-800',
    })[status] ?? 'bg-gray-100 text-gray-700'
}

// ── Actions ──────────────────────────────────────────────────────────────────

async function payer(): Promise<void> {
    erreurMsg.value = ''
    etape.value     = 'attente'
    tentatives      = 0

    try {
        const { data } = await axios.post('/abonnement/payer', {
            plan:   form.value.plan,
            duree:  form.value.duree,
            phone:  form.value.phone,
            devise: form.value.devise,
        })

        reference.value = data.reference
        demarrerPolling()
    } catch (err: any) {
        erreurMsg.value = err.response?.data?.message ?? 'Erreur lors de l\'initiation du paiement.'
        etape.value     = 'erreur'
    }
}

function demarrerPolling(): void {
    pollingId = setInterval(async () => {
        tentatives++

        if (tentatives > 60) {
            arreterPolling()
            etape.value = 'timeout'
            return
        }

        try {
            const { data } = await axios.get(`/api/abonnement/statut/${reference.value}`)

            if (data.status === 'confirmed') {
                arreterPolling()
                etape.value = 'succes'
            } else if (data.status === 'failed') {
                arreterPolling()
                erreurMsg.value = 'Le paiement a échoué. Réessayez.'
                etape.value     = 'erreur'
            }
        } catch {
            // réseau temporairement indisponible — continuer le polling
        }
    }, 5000)
}

function arreterPolling(): void {
    if (pollingId) {
        clearInterval(pollingId)
        pollingId = null
    }
}

function recharger(): void {
    window.location.reload()
}

function recommencer(): void {
    arreterPolling()
    erreurMsg.value = ''
    reference.value = ''
    etape.value     = 'formulaire'
}

onUnmounted(arreterPolling)
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

        <!-- État : FORMULAIRE -->
        <div v-if="etape === 'formulaire'" class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-5">Souscrire / Renouveler</h2>

            <form @submit.prevent="payer" class="space-y-5">

                <!-- Choix du plan -->
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="form.plan = 'premium'"
                            :class="['rounded-xl p-4 text-left transition',
                                     form.plan === 'premium' ? 'border-2 border-gray-900' : 'border border-gray-200']">
                        <p class="font-bold text-gray-800">Premium</p>
                        <p class="text-gray-900 font-bold text-lg">{{ prices.premium }} $/mois</p>
                        <p class="text-xs text-gray-500 mt-1">Illimité · Exports · Créances</p>
                    </button>
                    <button type="button" @click="form.plan = 'pro'"
                            :class="['rounded-xl p-4 text-left transition',
                                     form.plan === 'pro' ? 'border-2 border-gray-900' : 'border border-gray-200']">
                        <p class="font-bold text-gray-800">Pro</p>
                        <p class="text-gray-900 font-bold text-lg">{{ prices.pro }} $/mois</p>
                        <p class="text-xs text-gray-500 mt-1">Tout Premium · Succursales</p>
                    </button>
                </div>

                <!-- Durée -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée</label>
                    <select v-model.number="form.duree"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]">
                        <option v-for="n in 12" :key="n" :value="n">
                            {{ n }} mois
                            <template v-if="promo_prices[form.plan]?.[n]">
                                    — {{ promo_prices[form.plan]?.[n] }}$ (réduit)
                            </template>
                            <template v-else>
                                — {{ (prices[form.plan] ?? 0) * n }}$
                            </template>
                        </option>
                    </select>
                </div>

                <!-- Devise -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Devise</label>
                    <div class="flex gap-3">
                        <button type="button" @click="form.devise = 'USD'"
                                :class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
                                         form.devise === 'USD' ? 'border-[#1A56A0] bg-blue-50 text-[#1A56A0]' : 'border-gray-200 text-gray-600']">
                            USD ($)
                        </button>
                        <button type="button" @click="form.devise = 'CDF'"
                                :class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
                                         form.devise === 'CDF' ? 'border-[#1A56A0] bg-blue-50 text-[#1A56A0]' : 'border-gray-200 text-gray-600']">
                            CDF (FC)
                        </button>
                    </div>
                    <p v-if="form.devise === 'CDF'" class="text-xs text-gray-500 mt-1">
                        Taux appliqué : 1 $ = {{ usd_to_cdf_rate.toLocaleString('fr-FR') }} FC
                    </p>
                </div>

                <!-- Téléphone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Numéro Mobile Money
                    </label>
                    <input v-model="form.phone" type="tel" required
                           placeholder="Ex : 243 812 345 678"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]" />
                    <p class="text-xs text-gray-500 mt-1">MTN MoMo, Airtel Money, Orange Money, M-Pesa</p>
                </div>

                <!-- Récap montant -->
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700">
                    <div class="flex items-center justify-between">
                        <span>Total à payer</span>
                        <div class="text-right">
                            <span v-if="aReduction" class="line-through text-gray-400 text-xs mr-2">
                                {{ montantSansReduction }}
                            </span>
                            <strong class="text-gray-900 text-base">{{ montantAffiche }}</strong>
                        </div>
                    </div>
                    <p v-if="aReduction" class="text-green-600 text-xs mt-1 font-medium">
                        Offre spéciale {{ form.duree }} mois
                    </p>
                </div>

                <button type="submit"
                        :disabled="!form.phone"
                        class="w-full bg-[#1A56A0] hover:bg-[#0B2D5E] disabled:opacity-50 text-white font-semibold py-3 rounded-xl transition text-sm">
                    Payer {{ montantAffiche }} avec Netikash
                </button>

            </form>
        </div>

        <!-- État : EN ATTENTE -->
        <div v-if="etape === 'attente'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 mx-auto">
                <svg class="animate-spin w-8 h-8 text-[#1A56A0]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Confirmez sur votre téléphone</h3>
            <p class="text-gray-500 text-sm">
                Un message USSD a été envoyé sur votre numéro.<br>
                Acceptez le paiement pour activer votre abonnement.
            </p>
            <p class="text-xs text-gray-400">Vérification automatique en cours…</p>
        </div>

        <!-- État : SUCCÈS -->
        <div v-if="etape === 'succes'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-50 mx-auto">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Paiement confirmé !</h3>
            <p class="text-gray-500 text-sm">Votre abonnement est maintenant actif. Un email de confirmation vous a été envoyé.</p>
            <button @click="recharger"
                    class="bg-[#1A56A0] text-white px-6 py-2 rounded-xl text-sm font-semibold hover:bg-[#0B2D5E] transition">
                Voir mon plan
            </button>
        </div>

        <!-- État : TIMEOUT -->
        <div v-if="etape === 'timeout'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-50 mx-auto">
                <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Délai expiré</h3>
            <p class="text-gray-500 text-sm">
                Le paiement n'a pas été confirmé dans les 5 minutes.<br>
                Si vous avez accepté le paiement sur votre téléphone, l'abonnement s'activera automatiquement dès confirmation.
            </p>
            <button @click="recommencer"
                    class="bg-[#1A56A0] text-white px-6 py-2 rounded-xl text-sm font-semibold hover:bg-[#0B2D5E] transition">
                Réessayer
            </button>
        </div>

        <!-- État : ERREUR -->
        <div v-if="etape === 'erreur'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Paiement échoué</h3>
            <p class="text-gray-500 text-sm">{{ erreurMsg || 'Une erreur est survenue.' }}</p>
            <button @click="recommencer"
                    class="bg-[#1A56A0] text-white px-6 py-2 rounded-xl text-sm font-semibold hover:bg-[#0B2D5E] transition">
                Réessayer
            </button>
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
