<script setup>
import { reactive, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    entreprises:   { type: Array, default: () => [] },
    subscriptions: { type: Array, default: () => [] },
    historique:    { type: Array, default: () => [] },
})

const TARIFS = {
    premium: { 1: 7,  6: 40, 12: 70  },
    pro:     { 1: 10, 6: 55, 12: 100 },
    free:    { 1: 0,  6: 0,  12: 0   },
}

const forms = reactive({})
props.entreprises.forEach(e => {
    forms[e.id] = { plan: 'premium', duration: '1', trial_days: '0' }
})

function getPrix(plan, duration) {
    return TARIFS[plan]?.[parseInt(duration)] ?? 0
}

function getPrixLabel(plan, duration) {
    const p = getPrix(plan, duration)
    if (plan === 'free' || p === 0) return 'Gratuit'
    return `${p} $`
}

function activate(e) {
    const prix = getPrix(forms[e.id].plan, forms[e.id].duration)
    const trialDays = parseInt(forms[e.id].trial_days ?? 0)
    const isTrial = trialDays > 0

    const label = isTrial
        ? `Activer essai ${trialDays} jours (${forms[e.id].plan}) pour ${e.name} ?`
        : `Activer plan ${forms[e.id].plan} — ${getPrixLabel(forms[e.id].plan, forms[e.id].duration)} pour ${e.name} ?`

    if (!confirm(label)) return

    useForm({
        plan:              forms[e.id].plan,
        duration:          parseInt(forms[e.id].duration),
        trial_days:        trialDays,
        amount:            isTrial ? 0 : prix,
        payment_method:    'manual',
        payment_reference: 'admin-manual',
    }).post(`/owner/plans/${e.id}/activate`, {
        onSuccess: () => router.reload(),
        onError:   (err) => alert('Erreur: ' + JSON.stringify(err)),
    })
}

function downgrade(e) {
    if (!confirm(`Repasser ${e.name} en Free ?`)) return
    useForm({}).post(`/owner/plans/${e.id}/downgrade`, {
        onSuccess: () => router.reload(),
        onError:   (err) => alert('Erreur: ' + JSON.stringify(err)),
    })
}

function logout() {
    useForm({}).post('/owner/logout')
}

function planClass(plan) {
    return {
        'bg-gray-800 text-gray-400':        plan === 'free',
        'bg-blue-900/60 text-blue-300':     plan === 'premium',
        'bg-purple-900/60 text-purple-300': plan === 'pro',
    }
}

function statusClass(status) {
    return {
        'bg-green-900/50 text-green-300': status === 'confirmed',
        'bg-amber-900/50 text-amber-300': status === 'pending',
        'bg-red-900/50 text-red-300':     status === 'cancelled',
        'bg-blue-900/50 text-blue-300':   status === 'trial',
    }
}

// Stats
const totalRevenu = computed(() =>
    props.historique
        .filter(s => s.status === 'confirmed')
        .reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0)
)
</script>

<template>
  <div class="min-h-screen bg-gray-950 text-white">

    <!-- Header -->
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between sticky top-0 z-10">
      <div class="flex items-center gap-3">
        <span class="text-xl font-bold">
          <span class="text-yellow-400">Prime</span><span class="text-white">Gest</span>
        </span>
        <span class="text-gray-600 text-sm">/</span>
        <span class="text-gray-400 text-sm">Propriétaire</span>
      </div>
      <button @click="logout"
        class="text-xs text-gray-400 hover:text-white border border-gray-700 px-3 py-1.5 rounded-lg transition-colors">
        Déconnexion
      </button>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 space-y-10">

      <!-- Stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
          <div class="text-2xl font-bold text-white">{{ entreprises.length }}</div>
          <div class="text-xs text-gray-400 mt-1">Entreprises</div>
        </div>
        <div class="bg-gray-900 border border-blue-900/40 rounded-xl p-4">
          <div class="text-2xl font-bold text-blue-400">{{ entreprises.filter(e => e.plan === 'premium').length }}</div>
          <div class="text-xs text-gray-400 mt-1">Premium</div>
        </div>
        <div class="bg-gray-900 border border-purple-900/40 rounded-xl p-4">
          <div class="text-2xl font-bold text-purple-400">{{ entreprises.filter(e => e.plan === 'pro').length }}</div>
          <div class="text-xs text-gray-400 mt-1">Pro</div>
        </div>
        <div class="bg-gray-900 border border-green-900/40 rounded-xl p-4">
          <div class="text-2xl font-bold text-green-400">{{ totalRevenu.toFixed(0) }} $</div>
          <div class="text-xs text-gray-400 mt-1">Revenus confirmés</div>
        </div>
      </div>

      <!-- Grille tarifs -->
      <section>
        <h2 class="text-sm font-semibold text-gray-300 mb-3">Grille tarifaire</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Premium -->
          <div class="bg-gray-900 border border-blue-800/40 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="bg-blue-900/60 text-blue-300 px-2 py-0.5 rounded text-xs font-bold">PREMIUM</span>
            </div>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between text-gray-300">
                <span>1 mois</span><span class="font-mono text-white font-bold">7 $</span>
              </div>
              <div class="flex justify-between text-gray-300">
                <span>6 mois <span class="text-green-400 text-xs">(-5%)</span></span>
                <span class="font-mono text-white font-bold">40 $</span>
              </div>
              <div class="flex justify-between text-gray-300">
                <span>12 mois <span class="text-green-400 text-xs">(-17%)</span></span>
                <span class="font-mono text-white font-bold">70 $</span>
              </div>
            </div>
          </div>
          <!-- Pro -->
          <div class="bg-gray-900 border border-purple-800/40 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
              <span class="bg-purple-900/60 text-purple-300 px-2 py-0.5 rounded text-xs font-bold">PRO</span>
            </div>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between text-gray-300">
                <span>1 mois</span><span class="font-mono text-white font-bold">10 $</span>
              </div>
              <div class="flex justify-between text-gray-300">
                <span>6 mois <span class="text-green-400 text-xs">(-8%)</span></span>
                <span class="font-mono text-white font-bold">55 $</span>
              </div>
              <div class="flex justify-between text-gray-300">
                <span>12 mois <span class="text-green-400 text-xs">(-17%)</span></span>
                <span class="font-mono text-white font-bold">100 $</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Paiements en attente -->
      <section v-if="subscriptions.length">
        <h2 class="text-sm font-semibold text-amber-400 mb-3 flex items-center gap-2">
          <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
          Paiements en attente ({{ subscriptions.length }})
        </h2>
        <div class="bg-gray-900 border border-amber-800/40 rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-800 text-gray-400 text-xs">
                <tr>
                  <th class="text-left px-4 py-3">Entreprise</th>
                  <th class="text-left px-4 py-3">Plan</th>
                  <th class="text-left px-4 py-3">Montant</th>
                  <th class="text-left px-4 py-3">Méthode</th>
                  <th class="text-left px-4 py-3">Référence</th>
                  <th class="text-left px-4 py-3">Début</th>
                  <th class="text-left px-4 py-3">Expire</th>
                  <th class="text-left px-4 py-3">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="s in subscriptions" :key="s.id" class="hover:bg-gray-800/50">
                  <td class="px-4 py-3 font-medium">{{ s.entreprise }}</td>
                  <td class="px-4 py-3">
                    <span :class="planClass(s.plan)" class="px-2 py-0.5 rounded text-xs font-medium">{{ s.plan }}</span>
                  </td>
                  <td class="px-4 py-3 text-green-400 font-mono">{{ s.amount }} $</td>
                  <td class="px-4 py-3 text-gray-400 text-xs">{{ s.payment_method }}</td>
                  <td class="px-4 py-3 font-mono text-xs text-gray-300">{{ s.payment_reference }}</td>
                  <td class="px-4 py-3 text-gray-400 text-xs">{{ s.starts_at ?? '—' }}</td>
                  <td class="px-4 py-3 text-gray-400 text-xs">{{ s.expires_at ?? '—' }}</td>
                  <td class="px-4 py-3 text-gray-500 text-xs">{{ s.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Toutes les entreprises -->
      <section>
        <h2 class="text-sm font-semibold text-gray-300 mb-3">Toutes les entreprises</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-800 text-gray-400 text-xs">
                <tr>
                  <th class="text-left px-4 py-3">Entreprise</th>
                  <th class="text-left px-4 py-3">Plan actuel</th>
                  <th class="text-left px-4 py-3">Expire le</th>
                  <th class="text-left px-4 py-3">Nouveau plan</th>
                  <th class="text-left px-4 py-3">Durée</th>
                  <th class="text-left px-4 py-3">Prix</th>
                  <th class="text-left px-4 py-3">Essai (j)</th>
                  <th class="text-left px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="e in entreprises" :key="e.id" class="hover:bg-gray-800/40 transition-colors">
                  <td class="px-4 py-3 font-medium text-white">{{ e.name }}</td>
                  <td class="px-4 py-3">
                    <span :class="planClass(e.plan)" class="px-2 py-0.5 rounded text-xs font-medium">{{ e.plan }}</span>
                  </td>
                  <td class="px-4 py-3 text-gray-500 text-xs">{{ e.plan_expires_at ?? '—' }}</td>
                  <td class="px-4 py-3">
                    <select v-model="forms[e.id].plan"
                      class="bg-gray-800 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-yellow-400 outline-none">
                      <option value="premium">Premium</option>
                      <option value="pro">Pro</option>
                      <option value="free">Free</option>
                    </select>
                  </td>
                  <td class="px-4 py-3">
                    <select v-model="forms[e.id].duration"
                      class="bg-gray-800 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-yellow-400 outline-none">
                      <option value="1">1 mois</option>
                      <option value="6">6 mois</option>
                      <option value="12">12 mois</option>
                    </select>
                  </td>
                  <!-- Prix calculé automatiquement -->
                  <td class="px-4 py-3">
                    <span class="font-mono font-bold text-yellow-400 text-sm">
                      {{ getPrixLabel(forms[e.id].plan, forms[e.id].duration) }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <input v-model="forms[e.id].trial_days"
                      type="number" min="0" max="30" placeholder="0"
                      class="bg-gray-800 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-xs w-14 focus:ring-1 focus:ring-yellow-400 outline-none" />
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex gap-2">
                      <button @click="activate(e)"
                        class="bg-yellow-500 hover:bg-yellow-400 text-black text-xs px-3 py-1.5 rounded-lg font-bold transition-colors whitespace-nowrap">
                        Activer
                      </button>
                      <button @click="downgrade(e)"
                        class="border border-gray-700 hover:bg-gray-800 text-gray-400 hover:text-white text-xs px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
                        → Free
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Historique -->
      <section>
        <h2 class="text-sm font-semibold text-gray-300 mb-3">
          Historique des abonnements
          <span class="text-gray-600 font-normal ml-2">({{ historique.length }})</span>
        </h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
          <div v-if="historique.length === 0" class="px-4 py-10 text-center text-gray-600 text-sm">
            Aucun abonnement enregistré
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-800 text-gray-400 text-xs">
                <tr>
                  <th class="text-left px-4 py-3">Entreprise</th>
                  <th class="text-left px-4 py-3">Plan</th>
                  <th class="text-left px-4 py-3">Montant</th>
                  <th class="text-left px-4 py-3">Méthode</th>
                  <th class="text-left px-4 py-3">Statut</th>
                  <th class="text-left px-4 py-3">Début</th>
                  <th class="text-left px-4 py-3">Expire</th>
                  <th class="text-left px-4 py-3">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="s in historique" :key="'hist-' + s.id" class="hover:bg-gray-800/40">
                  <td class="px-4 py-3 font-medium text-white">{{ s.entreprise }}</td>
                  <td class="px-4 py-3">
                    <span :class="planClass(s.plan)" class="px-2 py-0.5 rounded text-xs font-medium">{{ s.plan }}</span>
                  </td>
                  <td class="px-4 py-3 text-green-400 font-mono text-xs">{{ s.amount }} $</td>
                  <td class="px-4 py-3 text-gray-400 text-xs">{{ s.payment_method }}</td>
                  <td class="px-4 py-3">
                    <span :class="statusClass(s.status)" class="px-2 py-0.5 rounded text-xs font-medium">{{ s.status }}</span>
                  </td>
                  <td class="px-4 py-3 text-gray-400 text-xs">{{ s.starts_at ?? '—' }}</td>
                  <td class="px-4 py-3 text-gray-400 text-xs">{{ s.expires_at ?? '—' }}</td>
                  <td class="px-4 py-3 text-gray-500 text-xs">{{ s.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>
