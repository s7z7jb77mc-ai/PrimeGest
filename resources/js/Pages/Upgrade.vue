<template>
  <div class="upgrade-page min-h-screen bg-white">

    <!-- ══ HEADER ══ -->
    <header class="flex items-center justify-between px-8 py-5 border-b border-gray-100">
      <!-- Retour -->
      <button @click="router.get('/dashboard')"
        class="flex items-center gap-2 text-sm text-gray-400 hover:text-gray-700 transition-colors font-mono tracking-wide">
        ← Retour
      </button>

      <!-- Logo PrimeGest — coin droit -->
      <div class="flex items-center gap-3">
        <div class="text-right">
          <div class="logo-text leading-none">
            <span class="text-gold font-display">Prime</span><span class="text-black font-display">Gest</span>
          </div>
          <div class="text-xs text-gray-400 font-mono tracking-widest text-right">GESTION PME</div>
        </div>
        <img src="/images/primegest.png"
          class="h-10 w-10 rounded-full object-cover ring-2 ring-gold/40 shadow-md shadow-yellow-200" />
      </div>
    </header>

    <!-- ══ HERO ══ -->
    <section class="text-center pt-14 pb-10 px-4">
      <!-- Badge contexte si redirigé depuis une feature bloquée -->
      <div v-if="feature" class="inline-flex items-center gap-2 mb-6 px-4 py-2 rounded-full border border-gray-200 bg-gray-50 text-sm text-gray-600 font-mono tracking-wide">
        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-pulse"></span>
        Fonctionnalité « {{ featureLabel }} » — Plan supérieur requis
      </div>

      <h1 class="font-display text-3xl md:text-4xl font-black text-black mb-3 leading-tight">
        Évoluez sans <span class="text-blue-600">limites</span>
      </h1>
      <p class="text-gray-400 text-base max-w-md mx-auto font-mono">
        Choisissez la durée qui vous convient. Payez une fois, profitez longtemps.
      </p>

      <!-- Toggle durée : 1 mois / 6 mois / 12 mois -->
      <div class="inline-flex items-center mt-8 bg-gray-50 border border-gray-200 rounded-full p-1">
        <button @click="billing = '1'"
          :class="billing === '1' ? 'bg-black text-white shadow' : 'text-gray-500 hover:text-black'"
          class="px-5 py-1.5 rounded-full text-sm font-mono font-medium transition-all">
          1 mois
        </button>
        <button @click="billing = '6'"
          :class="billing === '6' ? 'bg-black text-white shadow' : 'text-gray-500 hover:text-black'"
          class="px-5 py-1.5 rounded-full text-sm font-mono font-medium transition-all">
          6 mois
        </button>
        <button @click="billing = '12'"
          :class="billing === '12' ? 'bg-black text-white shadow' : 'text-gray-500 hover:text-black'"
          class="px-5 py-1.5 rounded-full text-sm font-mono font-medium transition-all">
          12 mois
          <span class="ml-1 text-xs text-blue-500 font-bold">-10%</span>
        </button>
      </div>
    </section>

    <!-- ══ PLANS ══ -->
    <section class="max-w-5xl mx-auto px-4 pb-16 grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- ─ FREE ─ -->
      <div :class="['plan-card', plan === 'free' ? 'ring-2 ring-black' : '']">
        <div class="plan-badge bg-gray-100 text-gray-500">Free</div>

        <div class="mt-6 mb-1">
          <span class="plan-price">0</span>
          <span class="plan-currency">$ / toujours</span>
        </div>
        <p class="text-xs text-gray-400 font-mono mb-7">Pour démarrer sans engagement</p>

        <ul class="plan-features mb-8">
          <li class="feat-on">3 utilisateurs</li>
          <li class="feat-on">50 produits</li>
          <li class="feat-on">20 clients / fournisseurs</li>
          <li class="feat-on">250 MB stockage</li>
          <li class="feat-on">Sync hors-ligne</li>
          <li class="feat-off">Gestion des dettes</li>
          <li class="feat-off">Réductions</li>
          <li class="feat-off">Exports PDF / Excel</li>
          <li class="feat-off">Succursales</li>
        </ul>

        <div v-if="plan === 'free'"
          class="w-full text-center py-2 rounded-lg border border-black text-black text-sm font-mono font-medium">
          Plan actuel
        </div>
      </div>

      <!-- ─ PREMIUM ─ -->
      <div class="plan-card plan-card--featured relative">
        <div class="plan-badge bg-gray-100 text-gray-700">Premium</div>

        <div class="mt-6 mb-1">
          <span class="plan-price">{{ billing === '1' ? '7' : billing === '6' ? '40' : '70' }}</span>
          <span class="plan-currency text-gray-400">$ / {{ billing === '1' ? 'mois' : billing === '6' ? '6 mois' : '12 mois' }}</span>
        </div>
        <p class="text-xs text-gray-400 font-mono mb-7">
          {{ billing === '1' ? '7 $/mois — sans engagement' : billing === '6' ? '≈ 6,67 $/mois' : '≈ 5,83 $/mois — économisez 14 $' }}
        </p>

        <ul class="plan-features mb-8">
          <li class="feat-on">Utilisateurs illimités</li>
          <li class="feat-on">Produits illimités</li>
          <li class="feat-on">Clients / fournisseurs illimités</li>
          <li class="feat-on">Stockage illimité</li>
          <li class="feat-on">Sync hors-ligne illimitée</li>
          <li class="feat-on">Gestion des dettes</li>
          <li class="feat-on">Réductions</li>
          <li class="feat-on">Exports PDF / Excel</li>
          <li class="feat-off">Succursales</li>
        </ul>

        <div v-if="plan === 'premium'"
          class="w-full text-center py-2 rounded-lg border border-blue-500 text-blue-500 text-sm font-mono font-medium">
          Plan actuel
        </div>
        <button v-else @click="allerAbonnement('premium')"
          class="w-full py-2.5 rounded-lg bg-[#1A56A0] text-white text-sm font-black font-mono tracking-wide hover:bg-[#0B2D5E] transition-all shadow-lg shadow-blue-200/60 active:scale-95">
          Passer au Premium →
        </button>
      </div>

      <!-- ─ PRO ─ -->
      <div :class="['plan-card', plan === 'pro' ? 'ring-2 ring-black' : '']">
        <div class="plan-badge bg-gray-100 text-gray-700">Pro</div>

        <div class="mt-6 mb-1">
          <span class="plan-price">{{ billing === '1' ? '10' : billing === '6' ? '55' : '100' }}</span>
          <span class="plan-currency">$ / {{ billing === '1' ? 'mois' : billing === '6' ? '6 mois' : '12 mois' }}</span>
        </div>
        <p class="text-xs text-gray-400 font-mono mb-7">
          {{ billing === '1' ? '10 $/mois — sans engagement' : billing === '6' ? '≈ 9,17 $/mois' : '≈ 8,33 $/mois — économisez 20 $' }}
        </p>

        <ul class="plan-features mb-8">
          <li class="feat-on">Tout Premium inclus</li>
          <li class="feat-on">Succursales illimitées</li>
          <li class="feat-on">Transferts inter-dépôts</li>
          <li class="feat-on">Dashboard agrégé multi-sites</li>
          <li class="feat-on">Rapports avancés consolidés</li>
        </ul>

        <div v-if="plan === 'pro'"
          class="w-full text-center py-2 rounded-lg border border-black text-black text-sm font-mono font-medium">
          Plan actuel
        </div>
        <button v-else @click="allerAbonnement('pro')"
          class="w-full py-2.5 rounded-lg border-2 border-black text-black text-sm font-black font-mono tracking-wide hover:bg-black hover:text-white transition-all active:scale-95">
          Passer au Pro →
        </button>
      </div>

    </section>

    <!-- ══ FOOTER ══ -->
    <footer class="border-t border-gray-100 py-5 text-center text-xs text-gray-300 font-mono">
      © {{ new Date().getFullYear() }} PrimeGest — Tous droits réservés
    </footer>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page    = usePage()
const plan    = computed(() => page.props.plan ?? 'free')
const feature = computed(() => page.props.feature ?? null)
const billing = ref('1')

const featureLabels = {
  dette_tracking:   'Créances & Dettes',
  reductions:       'Réductions',
  exports:          'Exports PDF/Excel',
  succursales:      'Succursales',
  users:            'Utilisateurs',
  produits:         'Produits',
  clients:          'Clients',
  fournisseurs:     'Fournisseurs',
  rapports:         'Rapports avancés',
}

const featureLabel = computed(() => featureLabels[feature.value] ?? feature.value)

function allerAbonnement(targetPlan) {
  router.visit('/abonnement', { data: { plan: targetPlan } })
}
</script>

<style scoped>
:root {
  --gold: #D4AF37;
}

.upgrade-page {
  font-family: 'DM Mono', monospace;
}

/* Logo */
.font-display { font-family: 'Playfair Display', serif; font-weight: 900; }
.logo-text { font-size: 1.4rem; letter-spacing: -0.02em; }
.text-gold  { color: var(--gold); }
.bg-gold    { background-color: var(--gold); }
.border-gold { border-color: var(--gold); }
.ring-gold  { --tw-ring-color: var(--gold); }
.shadow-yellow-200 { --tw-shadow-color: #fef08a40; }

/* Plan cards */
.plan-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 1.25rem;
  padding: 1.75rem;
  transition: box-shadow 0.2s, transform 0.2s;
  position: relative;
}
.plan-card:hover {
  box-shadow: 0 8px 32px rgba(0,0,0,0.07);
  transform: translateY(-2px);
}
.plan-card--featured {
  border: 2px solid #111;
}
.plan-card--featured:hover {
  box-shadow: 0 8px 32px rgba(0,0,0,0.07);
}

/* Badge plan */
.plan-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
}

/* Prix */
.plan-price {
  font-family: 'Playfair Display', serif;
  font-size: 2.8rem;
  font-weight: 900;
  color: #111;
  line-height: 1;
}
.plan-currency {
  font-size: 0.8rem;
  color: #9ca3af;
  margin-left: 0.25rem;
}

/* Features list */
.plan-features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  font-size: 0.8rem;
}
.feat-on, .feat-off {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.feat-on::before {
  content: '✓';
  color: var(--gold);
  font-weight: 900;
  flex-shrink: 0;
}
.feat-off {
  color: #d1d5db;
}
.feat-off::before {
  content: '—';
  color: #e5e7eb;
  flex-shrink: 0;
}

/* Gold pulse */
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.4; }
}
.animate-pulse { animation: pulse 1.5s ease-in-out infinite; }
</style>
