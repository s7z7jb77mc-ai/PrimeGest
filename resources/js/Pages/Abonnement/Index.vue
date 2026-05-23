<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
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

onMounted(() => {
    const params = new URLSearchParams(window.location.search)
    const planParam = params.get('plan')
    if (planParam === 'premium' || planParam === 'pro') {
        form.value.plan = planParam
    }
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

const joursWarning = computed(() => props.jours_restants !== null && props.jours_restants <= 7)

function statusLabel(status: string): string {
    return ({
        pending:   'En attente',
        confirmed: '✓ Confirmé',
        expired:   'Expiré',
        failed:    '✕ Échoué',
    })[status] ?? status
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
    if (pollingId) { clearInterval(pollingId); pollingId = null }
}

function recharger(): void { window.location.reload() }

function recommencer(): void {
    arreterPolling()
    erreurMsg.value = ''
    reference.value = ''
    etape.value     = 'formulaire'
}

onUnmounted(arreterPolling)
</script>

<template>
    <div class="pg-abo">

        <div class="pg-abo__wrap">

            <!-- En-tête plan actuel -->
            <div class="pg-card">
                <p class="pg-eyebrow">Plan actuel</p>
                <div class="pg-card__row">
                    <span class="pg-badge">{{ planLabel }}</span>
                    <span v-if="plan !== 'free' && plan_expires_at" class="pg-muted text-sm">
                        Valide jusqu'au
                        <strong>{{ new Date(plan_expires_at).toLocaleDateString('fr-FR') }}</strong>
                    </span>
                </div>
                <p v-if="joursWarning && plan !== 'free'" class="pg-warning mt-2 text-sm">
                    Expire dans <strong>{{ jours_restants }} jour{{ jours_restants !== 1 ? 's' : '' }}</strong> — pensez à renouveler.
                </p>
                <p v-if="plan === 'free'" class="pg-muted text-sm italic mt-2">
                    Passez à Premium ou Pro pour débloquer toutes les fonctionnalités.
                </p>
            </div>

            <!-- ── FORMULAIRE ── -->
            <div v-if="etape === 'formulaire'" class="pg-card">
                <h2 class="pg-heading">Souscrire / Renouveler</h2>

                <form @submit.prevent="payer" class="pg-form">

                    <!-- Choix du plan -->
                    <div>
                        <label class="pg-label">Plan</label>
                        <div class="pg-plan-grid">
                            <button type="button" @click="form.plan = 'premium'"
                                    :class="['pg-plan-btn', form.plan === 'premium' ? 'is-active' : '']">
                                <span class="pg-plan-btn__name">Premium</span>
                                <span class="pg-plan-btn__price">{{ prices.premium }} $/mois</span>
                                <span class="pg-plan-btn__desc">Illimité · Exports · Créances</span>
                            </button>
                            <button type="button" @click="form.plan = 'pro'"
                                    :class="['pg-plan-btn', form.plan === 'pro' ? 'is-active' : '']">
                                <span class="pg-plan-btn__name">Pro</span>
                                <span class="pg-plan-btn__price">{{ prices.pro }} $/mois</span>
                                <span class="pg-plan-btn__desc">Tout Premium · Succursales</span>
                            </button>
                        </div>
                    </div>

                    <!-- Durée -->
                    <div>
                        <label class="pg-label">Durée</label>
                        <select v-model.number="form.duree" class="pg-select">
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
                        <label class="pg-label">Devise</label>
                        <div class="pg-toggle-group">
                            <button type="button" @click="form.devise = 'USD'"
                                    :class="['pg-toggle-btn', form.devise === 'USD' ? 'is-active' : '']">
                                USD ($)
                            </button>
                            <button type="button" @click="form.devise = 'CDF'"
                                    :class="['pg-toggle-btn', form.devise === 'CDF' ? 'is-active' : '']">
                                CDF (FC)
                            </button>
                        </div>
                        <p v-if="form.devise === 'CDF'" class="pg-hint">
                            Taux appliqué : 1 $ = {{ usd_to_cdf_rate.toLocaleString('fr-FR') }} FC
                        </p>
                    </div>

                    <!-- Téléphone -->
                    <div>
                        <label class="pg-label">Numéro Mobile Money</label>
                        <input v-model="form.phone" type="tel" required
                               placeholder="Ex : 243 812 345 678"
                               class="pg-input" />
                        <p class="pg-hint">MTN MoMo, Airtel Money, Orange Money, M-Pesa</p>
                    </div>

                    <!-- Récap montant -->
                    <div class="pg-recap">
                        <div class="pg-recap__row">
                            <span>Total à payer</span>
                            <div>
                                <span v-if="aReduction" class="pg-recap__old">{{ montantSansReduction }}</span>
                                <strong class="pg-recap__total">{{ montantAffiche }}</strong>
                            </div>
                        </div>
                        <p v-if="aReduction" class="pg-recap__promo">
                            Offre spéciale {{ form.duree }} mois
                        </p>
                    </div>

                    <button type="submit" :disabled="!form.phone" class="pg-btn-primary">
                        Payer {{ montantAffiche }} avec Netikash
                    </button>

                </form>
            </div>

            <!-- ── EN ATTENTE ── -->
            <div v-if="etape === 'attente'" class="pg-card pg-state-card">
                <svg class="pg-spinner" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <h3 class="pg-heading">Confirmez sur votre téléphone</h3>
                <p class="pg-muted text-sm">
                    Un message USSD a été envoyé sur votre numéro.<br>
                    Acceptez le paiement pour activer votre abonnement.
                </p>
                <p class="pg-hint">Vérification automatique en cours…</p>
            </div>

            <!-- ── SUCCÈS ── -->
            <div v-if="etape === 'succes'" class="pg-card pg-state-card">
                <span class="pg-state-icon pg-state-icon--success">✓</span>
                <h3 class="pg-heading">Paiement confirmé !</h3>
                <p class="pg-muted text-sm">Votre abonnement est maintenant actif. Un email de confirmation vous a été envoyé.</p>
                <button @click="recharger" class="pg-btn-primary">Voir mon plan</button>
            </div>

            <!-- ── TIMEOUT ── -->
            <div v-if="etape === 'timeout'" class="pg-card pg-state-card">
                <span class="pg-state-icon">⏱</span>
                <h3 class="pg-heading">Délai expiré</h3>
                <p class="pg-muted text-sm">
                    Le paiement n'a pas été confirmé dans les 5 minutes.<br>
                    Si vous avez accepté le paiement, l'abonnement s'activera dès confirmation.
                </p>
                <button @click="recommencer" class="pg-btn-primary">Réessayer</button>
            </div>

            <!-- ── ERREUR ── -->
            <div v-if="etape === 'erreur'" class="pg-card pg-state-card">
                <span class="pg-state-icon">✕</span>
                <h3 class="pg-heading">Paiement échoué</h3>
                <p class="pg-muted text-sm">{{ erreurMsg || 'Une erreur est survenue.' }}</p>
                <button @click="recommencer" class="pg-btn-primary">Réessayer</button>
            </div>

            <!-- Historique -->
            <div v-if="historique.length" class="pg-card">
                <h2 class="pg-heading">Historique des abonnements</h2>
                <div class="overflow-x-auto mt-4">
                    <table class="pg-table">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Début</th>
                                <th>Fin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in historique" :key="s.id">
                                <td class="font-medium capitalize">{{ s.plan }}</td>
                                <td>{{ s.amount }} $</td>
                                <td>
                                    <span :class="['pg-status', s.status === 'confirmed' ? 'pg-status--ok' : 'pg-status--muted']">
                                        {{ statusLabel(s.status) }}
                                    </span>
                                </td>
                                <td>{{ s.starts_at ?? '—' }}</td>
                                <td>{{ s.expires_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</template>

<style scoped>
/* ══ Variables identiques à Home.vue ══ */
.pg-abo {
  --accent: #d69a1a;
  --accent-strong: #b9780f;
  --accent-soft: rgba(214, 154, 26, 0.14);
  --surface: rgba(255, 252, 246, 0.82);
  --surface-solid: #fffaf1;
  --text: #1d160f;
  --muted: rgba(76, 54, 26, 0.72);
  --soft: rgba(103, 82, 54, 0.56);
  --border: rgba(177, 130, 61, 0.18);
  --border-strong: rgba(177, 130, 61, 0.32);
  --shadow: 0 8px 32px rgba(52, 33, 9, 0.09);
}

.pg-abo__wrap {
  max-width: 42rem;
  margin-inline: auto;
  padding: 2rem 1rem 3rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* ── Card ── */
.pg-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 1.4rem;
  padding: 1.5rem;
  box-shadow: var(--shadow);
}

.pg-card__row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 0.4rem;
}

/* ── Typographie ── */
.pg-heading {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.01em;
  margin-bottom: 1.2rem;
}

.pg-eyebrow {
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--accent-strong);
}

.pg-muted { color: var(--muted); }
.pg-hint  { font-size: 0.78rem; color: var(--soft); margin-top: 0.35rem; }
.pg-warning { color: var(--accent-strong); font-weight: 500; }

/* ── Badge ── */
.pg-badge {
  display: inline-block;
  padding: 0.28rem 0.8rem;
  border-radius: 999px;
  background: var(--accent-soft);
  color: var(--accent-strong);
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

/* ── Form ── */
.pg-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.pg-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--muted);
  margin-bottom: 0.5rem;
  letter-spacing: 0.04em;
}

/* ── Plan buttons ── */
.pg-plan-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.pg-plan-btn {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  text-align: left;
  padding: 1rem 1.1rem;
  border-radius: 1rem;
  border: 1px solid var(--border);
  background: var(--surface-solid);
  cursor: pointer;
  transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
}
.pg-plan-btn:hover {
  border-color: var(--border-strong);
}
.pg-plan-btn.is-active {
  border: 2px solid var(--accent);
  background: linear-gradient(135deg, rgba(214, 154, 26, 0.08), var(--surface-solid));
  box-shadow: 0 0 0 3px var(--accent-soft);
}

.pg-plan-btn__name  { font-weight: 700; font-size: 0.95rem; color: var(--text); }
.pg-plan-btn__price { font-size: 1.05rem; font-weight: 700; color: var(--text); }
.pg-plan-btn__desc  { font-size: 0.74rem; color: var(--soft); }

/* ── Select ── */
.pg-select {
  width: 100%;
  padding: 0.65rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--surface-solid);
  color: var(--text);
  font-size: 0.88rem;
  outline: none;
  transition: border-color 0.18s, box-shadow 0.18s;
}
.pg-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}

/* ── Toggle groupe devise ── */
.pg-toggle-group {
  display: flex;
  gap: 0.5rem;
}

.pg-toggle-btn {
  flex: 1;
  padding: 0.6rem;
  border-radius: 0.75rem;
  border: 1px solid var(--border);
  background: var(--surface-solid);
  color: var(--muted);
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.18s, background 0.18s, color 0.18s, box-shadow 0.18s;
}
.pg-toggle-btn:hover { border-color: var(--border-strong); }
.pg-toggle-btn.is-active {
  border-color: var(--accent);
  background: linear-gradient(135deg, rgba(214, 154, 26, 0.08), var(--surface-solid));
  color: var(--accent-strong);
  box-shadow: 0 0 0 3px var(--accent-soft);
}

/* ── Input ── */
.pg-input {
  width: 100%;
  padding: 0.65rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  background: var(--surface-solid);
  color: var(--text);
  font-size: 0.88rem;
  outline: none;
  transition: border-color 0.18s, box-shadow 0.18s;
}
.pg-input::placeholder { color: var(--soft); }
.pg-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}

/* ── Récap ── */
.pg-recap {
  border-top: 1px solid var(--border);
  padding-top: 1rem;
}
.pg-recap__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.88rem;
  color: var(--muted);
}
.pg-recap__old   { text-decoration: line-through; color: var(--soft); font-size: 0.78rem; margin-right: 0.5rem; }
.pg-recap__total { font-size: 1.05rem; font-weight: 700; color: var(--text); }
.pg-recap__promo { font-size: 0.76rem; color: var(--accent-strong); margin-top: 0.3rem; font-weight: 600; letter-spacing: 0.04em; }

/* ── Bouton primaire — identique à Home.vue pg-link-button--primary ── */
.pg-btn-primary {
  width: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.85rem 1.5rem;
  border-radius: 999px;
  border: 0;
  cursor: pointer;
  font: inherit;
  font-weight: 700;
  font-size: 0.92rem;
  color: #19130c;
  background: linear-gradient(135deg, var(--accent) 0%, #ffd070 100%);
  box-shadow: 0 18px 36px rgba(214, 154, 26, 0.22);
  transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}
.pg-btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  background: linear-gradient(135deg, #b9780f 0%, #ffc94a 100%);
}
.pg-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── États (attente, succès, etc.) ── */
.pg-state-card {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.85rem;
  padding: 2.5rem 1.5rem;
}
.pg-state-card .pg-heading { margin-bottom: 0; }
.pg-state-card .pg-btn-primary { width: auto; padding-inline: 2rem; }

.pg-spinner {
  width: 2.2rem; height: 2.2rem;
  color: var(--accent);
  animation: spin 0.9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.pg-state-icon {
  font-size: 2.5rem;
  line-height: 1;
  color: var(--soft);
}
.pg-state-icon--success { color: var(--accent); }

/* ── Tableau historique ── */
.pg-table {
  width: 100%;
  font-size: 0.84rem;
  border-collapse: collapse;
}
.pg-table th {
  text-align: left;
  padding-bottom: 0.6rem;
  padding-right: 1rem;
  color: var(--soft);
  font-weight: 600;
  font-size: 0.76rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--border);
}
.pg-table td {
  padding: 0.6rem 1rem 0.6rem 0;
  color: var(--muted);
  border-bottom: 1px solid var(--border);
}
.pg-table tr:last-child td { border-bottom: none; }
.pg-table td.font-medium { color: var(--text); font-weight: 600; }

.pg-status { font-size: 0.76rem; letter-spacing: 0.04em; }
.pg-status--ok    { color: var(--accent-strong); font-weight: 600; }
.pg-status--muted { color: var(--soft); }
</style>
