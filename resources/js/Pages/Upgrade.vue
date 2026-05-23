<template>
  <div class="pg-upgrade">
    <div class="pg-upgrade__backdrop pg-upgrade__backdrop--top"></div>
    <div class="pg-upgrade__backdrop pg-upgrade__backdrop--side"></div>

    <!-- ══ HEADER ══ -->
    <header class="pg-header">
      <div class="pg-header__brand">
        <img src="/images/primegest.png" alt="PrimeGest" class="pg-header__logo" />
        <div class="pg-header__wordmark">
          <span>Prime</span>Gest
        </div>
      </div>

      <button @click="router.get('/dashboard')" class="pg-link-button pg-link-button--ghost">
        ← Tableau de bord
      </button>
    </header>

    <main>

      <!-- ══ HERO ══ -->
      <section class="pg-hero-section">
        <div v-if="feature" class="pg-pill">
          Fonctionnalité « {{ featureLabel }} » — Plan supérieur requis
        </div>

        <h1>Évoluez sans <span class="pg-accent">limites</span></h1>
        <p class="pg-subtitle">
          Choisissez la durée qui vous convient. Payez une fois, profitez longtemps.
        </p>

        <!-- Toggle durée -->
        <div class="pg-billing-toggle">
          <button @click="billing = '1'"
            :class="billing === '1' ? 'is-active' : ''"
            class="pg-billing-toggle__btn">
            1 mois
          </button>
          <button @click="billing = '6'"
            :class="billing === '6' ? 'is-active' : ''"
            class="pg-billing-toggle__btn">
            6 mois
          </button>
          <button @click="billing = '12'"
            :class="billing === '12' ? 'is-active' : ''"
            class="pg-billing-toggle__btn">
            12 mois
            <span class="pg-billing-toggle__promo">-10%</span>
          </button>
        </div>
      </section>

      <!-- ══ PLANS ══ -->
      <section class="pg-plan-section">

        <!-- ─ FREE ─ -->
        <article class="pg-plan-card" :class="{ 'is-current': plan === 'free' }">
          <span class="pg-section__eyebrow">Free</span>
          <div class="pg-plan-card__price">
            <strong>$0</strong>
            <span>pour toujours</span>
          </div>
          <p class="pg-plan-card__desc">Pour démarrer sans engagement</p>
          <ul>
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
          <div v-if="plan === 'free'" class="pg-link-button pg-link-button--outline pg-plan-card__cta">
            Plan actuel
          </div>
        </article>

        <!-- ─ PREMIUM ─ -->
        <article class="pg-plan-card is-featured" :class="{ 'is-current': plan === 'premium' }">
          <span class="pg-plan-card__badge">Le plus choisi</span>
          <span class="pg-section__eyebrow">Premium</span>
          <div class="pg-plan-card__price">
            <strong>${{ billing === '1' ? '7' : billing === '6' ? '40' : '70' }}</strong>
            <span>{{ billing === '1' ? 'par mois' : billing === '6' ? '/ 6 mois' : '/ 12 mois' }}</span>
          </div>
          <p class="pg-plan-card__desc">
            {{ billing === '1' ? '7 $/mois — sans engagement' : billing === '6' ? '≈ 6,67 $/mois' : '≈ 5,83 $/mois — économisez 14 $' }}
          </p>
          <ul>
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
          <div v-if="plan === 'premium'" class="pg-link-button pg-link-button--outline pg-plan-card__cta">
            Plan actuel
          </div>
          <button v-else @click="allerAbonnement('premium')"
            class="pg-link-button pg-link-button--primary pg-plan-card__cta">
            Passer au Premium →
          </button>
        </article>

        <!-- ─ PRO ─ -->
        <article class="pg-plan-card" :class="{ 'is-current': plan === 'pro' }">
          <span class="pg-section__eyebrow">Pro</span>
          <div class="pg-plan-card__price">
            <strong>${{ billing === '1' ? '10' : billing === '6' ? '55' : '100' }}</strong>
            <span>{{ billing === '1' ? 'par mois' : billing === '6' ? '/ 6 mois' : '/ 12 mois' }}</span>
          </div>
          <p class="pg-plan-card__desc">
            {{ billing === '1' ? '10 $/mois — sans engagement' : billing === '6' ? '≈ 9,17 $/mois' : '≈ 8,33 $/mois — économisez 20 $' }}
          </p>
          <ul>
            <li class="feat-on">Tout Premium inclus</li>
            <li class="feat-on">Succursales illimitées</li>
            <li class="feat-on">Transferts inter-dépôts</li>
            <li class="feat-on">Dashboard agrégé multi-sites</li>
            <li class="feat-on">Rapports avancés consolidés</li>
          </ul>
          <div v-if="plan === 'pro'" class="pg-link-button pg-link-button--outline pg-plan-card__cta">
            Plan actuel
          </div>
          <button v-else @click="allerAbonnement('pro')"
            class="pg-link-button pg-link-button--outline pg-plan-card__cta">
            Passer au Pro →
          </button>
        </article>

      </section>

    </main>

    <footer class="pg-footer-bar">
      © {{ new Date().getFullYear() }} PrimeGest — Tous droits réservés
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page    = usePage()
const plan    = computed(() => page.props.plan ?? 'free')
const feature = computed(() => page.props.feature ?? null)
const billing = ref('1')

const featureLabels: Record<string, string> = {
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

function allerAbonnement(targetPlan: string): void {
  router.visit(`/abonnement?plan=${targetPlan}`)
}
</script>

<style scoped>
/* ══ Variables identiques à Home.vue ══ */
.pg-upgrade {
  --page-bg: #f7efe1;
  --page-bg-strong: #fff8ec;
  --surface: rgba(255, 252, 246, 0.78);
  --surface-solid: #fffaf1;
  --surface-strong: #ffffff;
  --text: #1d160f;
  --muted: rgba(76, 54, 26, 0.72);
  --soft: rgba(103, 82, 54, 0.56);
  --border: rgba(177, 130, 61, 0.16);
  --border-strong: rgba(177, 130, 61, 0.3);
  --accent: #d69a1a;
  --accent-strong: #b9780f;
  --accent-soft: rgba(214, 154, 26, 0.14);
  --shadow: 0 24px 80px rgba(52, 33, 9, 0.12);

  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(240, 183, 68, 0.24), transparent 28%),
    radial-gradient(circle at right 20%, rgba(21, 35, 59, 0.12), transparent 24%),
    linear-gradient(180deg, var(--page-bg-strong) 0%, var(--page-bg) 48%, #efe5d5 100%);
  color: var(--text);
  position: relative;
  overflow-x: clip;
  padding: 1rem 0 3.5rem;
}

/* ── Backdrops ── */
.pg-upgrade__backdrop {
  position: absolute;
  border-radius: 999px;
  filter: blur(56px);
  pointer-events: none;
  opacity: 0.68;
}
.pg-upgrade__backdrop--top {
  width: 24rem; height: 24rem;
  top: -6rem; left: -4rem;
  background: rgba(241, 177, 45, 0.28);
}
.pg-upgrade__backdrop--side {
  width: 20rem; height: 20rem;
  right: -4rem; top: 18rem;
  background: rgba(60, 113, 186, 0.16);
}

/* ── Layout centré ── */
.pg-header,
.pg-hero-section,
.pg-plan-section,
.pg-footer-bar {
  width: min(1100px, calc(100% - 2rem));
  margin-inline: auto;
  position: relative;
  z-index: 1;
}

/* ── Header pill — identique à Home.vue ── */
.pg-header {
  position: sticky;
  top: 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem 0.9rem 1.15rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface-solid);
  box-shadow: var(--shadow);
}

.pg-header__brand {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.pg-header__logo {
  width: 3rem; height: 3rem;
  object-fit: contain;
  border-radius: 999px;
  background: rgba(10, 16, 24, 0.85);
  padding: 0.42rem;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.pg-header__wordmark {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.85rem;
  font-weight: 600;
  line-height: 1;
  letter-spacing: 0.02em;
  color: var(--text);
}
.pg-header__wordmark span { color: var(--accent); }

/* ── Boutons — identiques à Home.vue ── */
.pg-link-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.7rem;
  padding: 0.7rem 1.15rem;
  border-radius: 999px;
  text-decoration: none;
  font: inherit;
  cursor: pointer;
  border: 0;
  transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
.pg-link-button:hover { transform: translateY(-1px); }

.pg-link-button--ghost {
  color: var(--text);
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--border);
}
.pg-link-button--outline {
  color: var(--text);
  background: var(--surface-solid);
  border: 1px solid var(--border);
}
.pg-link-button--primary {
  color: #19130c;
  background: linear-gradient(135deg, var(--accent) 0%, #ffd070 100%);
  box-shadow: 0 18px 36px rgba(214, 154, 26, 0.22);
}

/* ── Hero ── */
.pg-hero-section {
  text-align: center;
  padding: clamp(2.5rem, 5vw, 5rem) 0 2rem;
}

.pg-hero-section h1 {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(2.6rem, 5vw, 4.5rem);
  font-weight: 600;
  line-height: 0.95;
  letter-spacing: -0.02em;
  margin: 1.2rem 0 1rem;
  color: var(--text);
}

.pg-accent { color: var(--accent); }

.pg-subtitle {
  color: var(--muted);
  font-size: 1.05rem;
  max-width: 38rem;
  margin: 0 auto 2rem;
  line-height: 1.7;
}

/* ── Pill eyebrow ── */
.pg-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.7rem 0.95rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: rgba(255, 255, 255, 0.35);
  color: var(--accent-strong);
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.pg-pill::before {
  content: '';
  width: 0.55rem; height: 0.55rem;
  border-radius: 999px;
  background: var(--accent);
  box-shadow: 0 0 0 0.35rem var(--accent-soft);
}

/* ── Toggle billing — style pg-lang-toggle ── */
.pg-billing-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.22rem;
  padding: 0.28rem;
  border-radius: 999px;
  background: var(--surface-solid);
  border: 1px solid var(--border);
}

.pg-billing-toggle__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 5.5rem;
  height: 2.2rem;
  padding: 0 1rem;
  border-radius: 999px;
  background: transparent;
  color: var(--soft);
  font-size: 0.84rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  border: 0;
  cursor: pointer;
  transition: background 0.18s, color 0.18s;
}
.pg-billing-toggle__btn.is-active {
  background: var(--accent);
  color: #1b140b;
}
.pg-billing-toggle__promo {
  font-size: 0.72rem;
  color: var(--accent-strong);
  font-weight: 900;
}
.pg-billing-toggle__btn.is-active .pg-billing-toggle__promo {
  color: #1b140b;
}

/* ── Plans grid — identique à Home.vue pg-plan-grid ── */
.pg-plan-section {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  padding-top: 2rem;
  padding-bottom: 2rem;
}

.pg-section__eyebrow {
  display: inline-block;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--accent-strong);
  margin-bottom: 0.7rem;
}

/* ── Plan card — identique à Home.vue pg-plan-card ── */
.pg-plan-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
  border-radius: 1.6rem;
  padding: 1.35rem;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.pg-plan-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 32px 80px rgba(52, 33, 9, 0.15);
}

.pg-plan-card.is-featured {
  background: linear-gradient(180deg, rgba(240, 187, 69, 0.14), var(--surface));
  border-color: var(--border-strong);
  transform: translateY(-0.2rem);
}
.pg-plan-card.is-featured:hover {
  transform: translateY(-0.5rem);
}

.pg-plan-card.is-current {
  border-color: var(--accent);
  box-shadow: 0 0 0 2px var(--accent-soft), var(--shadow);
}

.pg-plan-card__badge {
  display: inline-block;
  padding: 0.45rem 0.7rem;
  border-radius: 999px;
  background: var(--accent-soft);
  letter-spacing: 0.12em;
  text-transform: uppercase;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--accent-strong);
  align-self: flex-start;
}

.pg-plan-card__price {
  display: flex;
  align-items: baseline;
  gap: 0.7rem;
}
.pg-plan-card__price strong {
  font-family: 'Cormorant Garamond', serif;
  font-size: 3rem;
  line-height: 1;
  color: var(--text);
}
.pg-plan-card__price span {
  color: var(--muted);
  font-size: 0.9rem;
}

.pg-plan-card__desc {
  color: var(--muted);
  font-size: 0.82rem;
  line-height: 1.5;
  margin: -0.5rem 0 0;
}

.pg-plan-card ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.65rem;
  flex: 1;
}

.pg-plan-card h3 {
  margin: 0 0 0.8rem;
  font-size: 1.24rem;
  color: var(--text);
}

/* ── Features list ── */
.feat-on,
.feat-off {
  position: relative;
  padding-left: 1.3rem;
  font-size: 0.82rem;
  color: var(--muted);
  line-height: 1.5;
}
.feat-on::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.6rem;
  width: 0.4rem; height: 0.4rem;
  border-radius: 999px;
  background: var(--accent);
}
.feat-off {
  color: var(--soft);
  opacity: 0.55;
}
.feat-off::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.6rem;
  width: 0.4rem; height: 0.4rem;
  border-radius: 999px;
  background: var(--border-strong);
}

.pg-plan-card__cta {
  width: 100%;
  margin-top: auto;
  font-weight: 700;
  font-size: 0.9rem;
  justify-content: center;
  text-align: center;
}

/* ── Footer ── */
.pg-footer-bar {
  text-align: center;
  padding: 2rem 0 0;
  font-size: 0.8rem;
  color: var(--soft);
  border-top: 1px solid var(--border);
}

/* ── Responsive ── */
@media (max-width: 860px) {
  .pg-plan-section {
    grid-template-columns: 1fr;
    max-width: 28rem;
  }
  .pg-plan-card.is-featured {
    transform: none;
  }
}

@media (max-width: 520px) {
  .pg-header { border-radius: 1.35rem; }
  .pg-header__wordmark { font-size: 1.5rem; }
  .pg-billing-toggle { flex-wrap: wrap; border-radius: 1.2rem; }
}

@media (prefers-reduced-motion: reduce) {
  .pg-link-button,
  .pg-plan-card { transition: none; }
}
</style>
