<script setup lang="ts">
import { computed, ref } from 'vue'
import { getStoredTheme, setTheme } from '@/theme'
import { setLang } from '@/lang'
import { useLang } from '@/composables/useLang'

type Lang = 'fr' | 'en'
type Theme = 'light' | 'dark'

const lang = useLang()
const theme = ref<Theme>(getStoredTheme())
const registerUrl = '/register-entreprise'

const copy = computed(() => content[(lang.value === 'en' ? 'en' : 'fr') as Lang])
const year = new Date().getFullYear()

function switchLang(next: Lang) {
  setLang(next)
}

function toggleTheme() {
  const next: Theme = theme.value === 'dark' ? 'light' : 'dark'
  theme.value = next
  setTheme(next)
}

const content = {
  fr: {
    nav: {
      product: 'Produit',
      features: 'Modules',
      pricing: 'Tarifs',
      login: 'Connexion',
      register: 'Essai gratuit',
    },
    hero: {
      eyebrow: 'Gestion commerciale pour les Petites et Moyennes Entreprises en croissance',
      title: 'Votre entreprise, enfin pilotée avec clarté.',
      subtitle: 'PrimeGest réunit stocks, caisse, clients, fournisseurs et équipes dans une interface fluide, bilingue et pensée pour votre quotidien — que vous soyez au bureau ou sur le terrain.',
      primary: 'Créer mon espace gratuit',
      secondary: 'Se connecter',
      note: 'Aucune carte requise · Opérationnel en quelques minutes.',
      cardOneTitle: 'Vue centrale en direct',
      cardOneText: 'Ventes, achats, caisse et alertes stock réunis en un seul tableau de bord.',
      cardTwoTitle: 'Parfait sur mobile',
      cardTwoText: 'Aussi confortable sur téléphone, tablette que sur desktop.',
    },
    stats: [
      { value: '24/7', label: 'Suivi continu de vos opérations et alertes en temps réel' },
      { value: 'Multi-sites', label: 'Central, succursales et équipes dans le même flux' },
      { value: 'FR / EN', label: 'Bascule de langue instantanée depuis la barre de navigation' },
    ],
    features: {
      title: 'Tout ce dont vous avez besoin, rien de superflu.',
      subtitle: 'Chaque module de PrimeGest a été conçu pour les entrepreneurs qui n\'ont pas de temps à perdre — des écrans clairs, des actions rapides, zéro friction.',
      items: [
        {
          label: 'Stocks',
          title: 'Vos produits sous contrôle, en temps réel',
          description: 'Entrées, sorties, seuils d\'alerte et valorisation — soyez informé avant la rupture, pas après.',
        },
        {
          label: 'Facturation',
          title: 'Factures et bons en quelques secondes',
          description: 'Un flux de vente plus rapide, des documents propres et un historique toujours accessible.',
        },
        {
          label: 'Trésorerie',
          title: 'Caisse et journal parfaitement liés',
          description: 'Chaque mouvement trouve sa place. Lecture simple pour le central comme pour les équipes terrain.',
        },
        {
          label: 'Clients & fournisseurs',
          title: 'Toutes vos relations commerciales, au même endroit',
          description: 'Historiques, créances, dettes et contacts — une vue complète sur chaque partenaire commercial.',
        },
        {
          label: 'RH',
          title: 'Employés, paie et droits d\'accès simplifiés',
          description: 'Fiches de paie, congés, rôles et responsabilités — sans bricolage, sans tableur annexe.',
        },
        {
          label: 'Succursales',
          title: 'Pilotez plusieurs sites sans vous éparpiller',
          description: 'Du central aux succursales, une expérience cohérente avec des transferts et des rapports centralisés.',
        },
      ],
    },
    workflow: {
      title: 'Conçu pour un usage terrain, pas pour une démo.',
      subtitle: 'PrimeGest est bâti autour des réalités du quotidien — connexion instable, équipes mobiles, données critiques. Il s\'adapte à vous, pas l\'inverse.',
      steps: [
        {
          number: '01',
          title: 'Démarrez en quelques minutes',
          description: 'Créez votre espace, invitez votre équipe et importez vos données sans assistance technique.',
        },
        {
          number: '02',
          title: 'Travaillez même hors ligne',
          description: 'La version desktop continue de fonctionner sans internet. Vos données se synchronisent dès le retour de la connexion.',
        },
        {
          number: '03',
          title: 'Décidez avec confiance',
          description: 'Tableaux de bord clairs, rapports précis et alertes proactives pour ne jamais être pris par surprise.',
        },
      ],
    },
    pricing: {
      title: 'Un plan pour chaque étape de votre croissance.',
      subtitle: 'Commencez gratuitement, évoluez quand vous êtes prêt. Aucun engagement, aucune surprise.',
      plans: [
        {
          name: 'Free',
          price: '$0',
          period: 'pour toujours',
          cta: 'Démarrer gratuitement',
          featured: false,
          features: ['3 utilisateurs', '50 produits', 'Factures & bons de caisse', 'Journal des mouvements'],
        },
        {
          name: 'Premium',
          price: '$7',
          period: 'par mois',
          cta: 'Passer en Premium',
          featured: true,
          features: ['Utilisateurs illimités', 'Clients & fournisseurs illimités', 'Suivi créances et dettes', 'Exports et support prioritaire'],
        },
        {
          name: 'Pro',
          price: '$10',
          period: 'par mois',
          cta: 'Activer Pro',
          featured: false,
          features: ['Tout ce qu\'inclut Premium', 'Gestion multi-succursales', 'Transferts inter-sites', 'Tableau de bord centralisé'],
        },
      ],
      badge: 'Le plus choisi',
    },
    cta: {
      title: 'Prêt à prendre le contrôle de votre gestion ?',
      subtitle: 'Rejoignez des entrepreneurs qui font confiance à PrimeGest pour piloter leur activité — chaque jour, sur tous leurs écrans.',
      button: 'Créer mon compte gratuit',
    },
    footer: {
      product: 'Produit',
      contact: 'Contact',
      rights: 'Tous droits réservés',
    },
  },
  en: {
    nav: {
      product: 'Product',
      features: 'Modules',
      pricing: 'Pricing',
      login: 'Login',
      register: 'Free trial',
    },
    hero: {
      eyebrow: 'Business management for SMEs',
      title: 'Your business, finally run with clarity.',
      subtitle: 'PrimeGest brings stock, cash flow, customers, suppliers and teams together in one fluid, bilingual interface — built for your daily reality, whether you\'re at HQ or in the field.',
      primary: 'Create my free workspace',
      secondary: 'Sign in',
      note: 'No card required · Up and running in minutes.',
      cardOneTitle: 'Live headquarters view',
      cardOneText: 'Sales, purchasing, cash flow and stock alerts — all in one dashboard.',
      cardTwoTitle: 'Perfect on mobile',
      cardTwoText: 'Just as comfortable on phone, tablet or desktop.',
    },
    stats: [
      { value: '24/7', label: 'Continuous tracking for your operations and real-time alerts' },
      { value: 'Multi-site', label: 'Headquarters, branches and teams in the same flow' },
      { value: 'FR / EN', label: 'Instant language switch directly from the navigation bar' },
    ],
    features: {
      title: 'Everything you need. Nothing you don\'t.',
      subtitle: 'Every PrimeGest module was built for entrepreneurs who can\'t afford to lose time — clear screens, fast actions, zero friction.',
      items: [
        {
          label: 'Stock',
          title: 'Your products under control, in real time',
          description: 'Entries, exits, alert thresholds and valuation — know about shortages before they happen, not after.',
        },
        {
          label: 'Billing',
          title: 'Invoices and receipts in seconds',
          description: 'A faster sales flow, cleaner documents and a history that\'s always easy to reach.',
        },
        {
          label: 'Cash flow',
          title: 'Cash desk and journal fully aligned',
          description: 'Every movement in its right place — simple reading for HQ and field teams alike.',
        },
        {
          label: 'Customers & suppliers',
          title: 'All your business relationships, in one place',
          description: 'History, receivables, payables and contacts — a complete picture of every commercial partner.',
        },
        {
          label: 'HR',
          title: 'Employees, payroll and access rights made simple',
          description: 'Payslips, leave, roles and responsibilities — no patchwork, no side spreadsheets.',
        },
        {
          label: 'Branches',
          title: 'Run multiple sites without losing focus',
          description: 'From HQ to branches, a consistent experience with inter-site transfers and centralised reporting.',
        },
      ],
    },
    workflow: {
      title: 'Built for real field usage, not just demos.',
      subtitle: 'PrimeGest is designed around everyday realities — unstable connections, mobile teams, critical data. It adapts to you, not the other way around.',
      steps: [
        {
          number: '01',
          title: 'Get started in minutes',
          description: 'Create your workspace, invite your team and import your data — no technical help needed.',
        },
        {
          number: '02',
          title: 'Keep working offline',
          description: 'The desktop version runs without internet. Your data syncs automatically when the connection returns.',
        },
        {
          number: '03',
          title: 'Decide with confidence',
          description: 'Clear dashboards, precise reports and proactive alerts — so you\'re never caught off guard.',
        },
      ],
    },
    pricing: {
      title: 'A plan for every stage of your growth.',
      subtitle: 'Start free, upgrade when you\'re ready. No commitment, no surprises.',
      plans: [
        {
          name: 'Free',
          price: '$0',
          period: 'forever',
          cta: 'Start for free',
          featured: false,
          features: ['3 users', '50 products', 'Invoices & cash receipts', 'Movement journal'],
        },
        {
          name: 'Premium',
          price: '$7',
          period: 'per month',
          cta: 'Go Premium',
          featured: true,
          features: ['Unlimited users', 'Unlimited customers & suppliers', 'Debt & receivables tracking', 'Exports and priority support'],
        },
        {
          name: 'Pro',
          price: '$10',
          period: 'per month',
          cta: 'Enable Pro',
          featured: false,
          features: ['Everything in Premium', 'Multi-branch management', 'Inter-site transfers', 'Centralised dashboard'],
        },
      ],
      badge: 'Most popular',
    },
    cta: {
      title: 'Ready to take control of your operations?',
      subtitle: 'Join entrepreneurs who trust PrimeGest to run their business — every day, on every screen.',
      button: 'Create my free account',
    },
    footer: {
      product: 'Product',
      contact: 'Contact',
      rights: 'All rights reserved',
    },
  },
} as const
</script>

<template>
  <div class="pg-home" :data-theme="theme">
    <div class="pg-home__backdrop pg-home__backdrop--top"></div>
    <div class="pg-home__backdrop pg-home__backdrop--side"></div>

    <header class="pg-header">
      <div class="pg-header__brand">
        <img src="/images/primegest.png" alt="PrimeGest" class="pg-header__logo" />
        <div class="pg-header__wordmark">
          <span>Prime</span>Gest
        </div>
      </div>

      <nav class="pg-header__nav" aria-label="Primary">
        <a href="#features">{{ copy.nav.features }}</a>
        <a href="#workflow">{{ copy.nav.product }}</a>
        <a href="#pricing">{{ copy.nav.pricing }}</a>
      </nav>

      <div class="pg-header__actions">
        <div class="pg-lang-toggle" aria-label="Language switcher">
          <button
            type="button"
            class="pg-lang-toggle__button"
            :class="{ 'is-active': lang === 'fr' }"
            @click="switchLang('fr')"
          >
            FR
          </button>
          <button
            type="button"
            class="pg-lang-toggle__button"
            :class="{ 'is-active': lang === 'en' }"
            @click="switchLang('en')"
          >
            EN
          </button>
        </div>

        <button type="button" class="pg-theme-toggle" @click="toggleTheme" :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'">
          <span>{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
        </button>

        <a href="/login" class="pg-link-button pg-link-button--ghost">{{ copy.nav.login }}</a>
        <a :href="registerUrl" class="pg-link-button pg-link-button--primary">{{ copy.nav.register }}</a>
      </div>
    </header>

    <main>
      <section class="pg-hero">
        <div class="pg-hero__copy">
          <span class="pg-pill">{{ copy.hero.eyebrow }}</span>
          <h1>{{ copy.hero.title }}</h1>
          <p class="pg-hero__subtitle">{{ copy.hero.subtitle }}</p>

          <div class="pg-hero__cta">
            <a :href="registerUrl" class="pg-link-button pg-link-button--primary pg-link-button--large">{{ copy.hero.primary }}</a>
            <a href="/login" class="pg-link-button pg-link-button--outline pg-link-button--large">{{ copy.hero.secondary }}</a>
          </div>

          <p class="pg-hero__note">{{ copy.hero.note }}</p>

          <div class="pg-stats">
            <article v-for="stat in copy.stats" :key="stat.label" class="pg-stat-card">
              <strong>{{ stat.value }}</strong>
              <span>{{ stat.label }}</span>
            </article>
          </div>
        </div>

        <div class="pg-hero__visual">
          <div class="pg-hero__frame">
            <img src="/images/pris.png" alt="PrimeGest dashboard preview" class="pg-hero__image" />
          </div>

          <aside class="pg-floating-card pg-floating-card--left">
            <span class="pg-floating-card__label">{{ copy.hero.cardOneTitle }}</span>
            <p>{{ copy.hero.cardOneText }}</p>
          </aside>

          <aside class="pg-floating-card pg-floating-card--right">
            <span class="pg-floating-card__label">{{ copy.hero.cardTwoTitle }}</span>
            <p>{{ copy.hero.cardTwoText }}</p>
          </aside>
        </div>
      </section>

      <section id="features" class="pg-section">
        <div class="pg-section__header">
          <span class="pg-section__eyebrow">{{ copy.nav.features }}</span>
          <h2>{{ copy.features.title }}</h2>
          <p>{{ copy.features.subtitle }}</p>
        </div>

        <div class="pg-feature-grid">
          <article v-for="feature in copy.features.items" :key="feature.title" class="pg-feature-card">
            <span class="pg-feature-card__label">{{ feature.label }}</span>
            <h3>{{ feature.title }}</h3>
            <p>{{ feature.description }}</p>
          </article>
        </div>
      </section>

      <section id="workflow" class="pg-section pg-section--workflow">
        <div class="pg-section__header">
          <span class="pg-section__eyebrow">{{ copy.nav.product }}</span>
          <h2>{{ copy.workflow.title }}</h2>
          <p>{{ copy.workflow.subtitle }}</p>
        </div>

        <div class="pg-step-grid">
          <article v-for="step in copy.workflow.steps" :key="step.number" class="pg-step-card">
            <span class="pg-step-card__number">{{ step.number }}</span>
            <h3>{{ step.title }}</h3>
            <p>{{ step.description }}</p>
          </article>
        </div>
      </section>

      <section id="pricing" class="pg-section pg-section--pricing">
        <div class="pg-section__header">
          <span class="pg-section__eyebrow">{{ copy.nav.pricing }}</span>
          <h2>{{ copy.pricing.title }}</h2>
          <p>{{ copy.pricing.subtitle }}</p>
        </div>

        <div class="pg-plan-grid">
          <article
            v-for="plan in copy.pricing.plans"
            :key="plan.name"
            class="pg-plan-card"
            :class="{ 'is-featured': plan.featured }"
          >
            <span v-if="plan.featured" class="pg-plan-card__badge">{{ copy.pricing.badge }}</span>
            <h3>{{ plan.name }}</h3>
            <div class="pg-plan-card__price">
              <strong>{{ plan.price }}</strong>
              <span>{{ plan.period }}</span>
            </div>
            <ul>
              <li v-for="feature in plan.features" :key="feature">{{ feature }}</li>
            </ul>
            <a :href="registerUrl" class="pg-link-button" :class="plan.featured ? 'pg-link-button--primary' : 'pg-link-button--outline'">
              {{ plan.cta }}
            </a>
          </article>
        </div>
      </section>

      <section class="pg-cta-section">
        <div class="pg-cta-section__content">
          <h2>{{ copy.cta.title }}</h2>
          <p>{{ copy.cta.subtitle }}</p>
        </div>
        <a :href="registerUrl" class="pg-link-button pg-link-button--primary pg-link-button--large">
          {{ copy.cta.button }}
        </a>
      </section>
    </main>

    <footer class="pg-footer">
      <div class="pg-footer__brand">
        <img src="/images/primegest.png" alt="PrimeGest" class="pg-footer__logo" />
        <div>
          <div class="pg-footer__name">PrimeGest</div>
          <p>© {{ year }} · {{ copy.footer.rights }}</p>
        </div>
      </div>

      <div class="pg-footer__links">
        <div>
          <span>{{ copy.footer.product }}</span>
          <a :href="registerUrl">{{ copy.nav.register }}</a>
          <a href="/login">{{ copy.nav.login }}</a>
        </div>
        <div>
          <span>{{ copy.footer.contact }}</span>
          <a href="mailto:support@primegest.app">support@primegest.app</a>
          <a href="https://primegest.app">primegest.app</a>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.pg-home {
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
  --hero-shadow: 0 42px 80px rgba(39, 23, 6, 0.18);
  --glass: rgba(255, 250, 241, 0.58);
  --glass-stroke: rgba(255, 255, 255, 0.45);
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

.pg-home[data-theme='dark'] {
  --page-bg: #0a111a;
  --page-bg-strong: #101926;
  --surface: rgba(13, 21, 31, 0.76);
  --surface-solid: #101824;
  --surface-strong: #131d2b;
  --text: #f3eadc;
  --muted: rgba(233, 221, 203, 0.76);
  --soft: rgba(233, 221, 203, 0.58);
  --border: rgba(227, 176, 88, 0.16);
  --border-strong: rgba(227, 176, 88, 0.3);
  --accent: #f0bb45;
  --accent-strong: #ffd27c;
  --accent-soft: rgba(240, 187, 69, 0.14);
  --shadow: 0 32px 90px rgba(0, 0, 0, 0.38);
  --hero-shadow: 0 42px 90px rgba(0, 0, 0, 0.45);
  --glass: rgba(11, 18, 27, 0.62);
  --glass-stroke: rgba(255, 255, 255, 0.06);
}

.pg-home__backdrop {
  position: absolute;
  border-radius: 999px;
  filter: blur(56px);
  pointer-events: none;
  opacity: 0.68;
}

.pg-home__backdrop--top {
  width: 24rem;
  height: 24rem;
  top: -6rem;
  left: -4rem;
  background: rgba(241, 177, 45, 0.28);
}

.pg-home__backdrop--side {
  width: 20rem;
  height: 20rem;
  right: -4rem;
  top: 18rem;
  background: rgba(60, 113, 186, 0.16);
}

.pg-header,
.pg-hero,
.pg-section,
.pg-cta-section,
.pg-footer {
  width: min(1180px, calc(100% - 2rem));
  margin-inline: auto;
  position: relative;
  z-index: 1;
}

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
  background: var(--glass);
  backdrop-filter: blur(26px);
  -webkit-backdrop-filter: blur(26px);
  box-shadow: var(--shadow);
}

.pg-header__brand,
.pg-footer__brand {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.pg-header__logo,
.pg-footer__logo {
  width: 2.45rem;
  height: 2.45rem;
  object-fit: contain;
  border-radius: 999px;
  background: rgba(10, 16, 24, 0.85);
  padding: 0.38rem;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.pg-header__wordmark,
.pg-footer__name {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.6rem;
  font-weight: 600;
  line-height: 1;
  letter-spacing: 0.02em;
}

.pg-header__wordmark span {
  color: var(--accent);
}

.pg-header__nav,
.pg-header__actions,
.pg-lang-toggle,
.pg-hero__cta,
.pg-stats,
.pg-section__header,
.pg-plan-card__price,
.pg-footer,
.pg-footer__links {
  display: flex;
  align-items: center;
}

.pg-header__nav {
  gap: 1.3rem;
}

.pg-header__nav a,
.pg-footer__links a {
  color: var(--muted);
  text-decoration: none;
  font-size: 0.95rem;
  transition: color 0.2s ease;
}

.pg-header__nav a:hover,
.pg-footer__links a:hover {
  color: var(--text);
}

.pg-header__actions {
  gap: 0.65rem;
}

.pg-lang-toggle {
  gap: 0.22rem;
  padding: 0.22rem;
  border-radius: 999px;
  background: var(--surface-solid);
  border: 1px solid var(--border);
}

.pg-lang-toggle__button,
.pg-theme-toggle,
.pg-link-button {
  border: 0;
  cursor: pointer;
  font: inherit;
}

.pg-lang-toggle__button {
  min-width: 2.55rem;
  height: 2.2rem;
  border-radius: 999px;
  background: transparent;
  color: var(--soft);
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.08em;
}

.pg-lang-toggle__button.is-active {
  background: var(--accent);
  color: #1b140b;
}

.pg-theme-toggle {
  height: 2.4rem;
  padding: 0 0.9rem;
  border-radius: 999px;
  background: var(--surface-solid);
  color: var(--text);
  border: 1px solid var(--border);
  font-size: 0.84rem;
  font-weight: 600;
}

.pg-link-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  min-height: 2.7rem;
  padding: 0.7rem 1.15rem;
  border-radius: 999px;
  text-decoration: none;
  transition:
    transform 0.2s ease,
    background 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.pg-link-button:hover,
.pg-theme-toggle:hover,
.pg-lang-toggle__button:hover {
  transform: translateY(-1px);
}

.pg-link-button--ghost,
.pg-link-button--outline {
  color: var(--text);
  background: transparent;
  border: 1px solid var(--border);
}

.pg-link-button--ghost {
  background: rgba(255, 255, 255, 0.02);
}

.pg-link-button--outline {
  background: var(--surface-solid);
}

.pg-link-button--primary {
  color: #19130c;
  background: linear-gradient(135deg, var(--accent) 0%, #ffd070 100%);
  box-shadow: 0 18px 36px rgba(214, 154, 26, 0.22);
}

.pg-link-button--large {
  min-height: 3.2rem;
  padding-inline: 1.35rem;
  font-weight: 700;
}

.pg-hero {
  display: grid;
  grid-template-columns: minmax(0, 1.02fr) minmax(0, 0.98fr);
  align-items: center;
  gap: clamp(2rem, 5vw, 4.25rem);
  padding: clamp(2.2rem, 5vw, 4.5rem) 0 2rem;
}

.pg-hero__copy h1,
.pg-section__header h2,
.pg-cta-section h2 {
  font-family: 'Cormorant Garamond', serif;
  font-weight: 600;
  letter-spacing: -0.02em;
}

.pg-pill,
.pg-section__eyebrow,
.pg-feature-card__label,
.pg-floating-card__label,
.pg-plan-card__badge,
.pg-step-card__number {
  letter-spacing: 0.12em;
  text-transform: uppercase;
  font-size: 0.76rem;
  font-weight: 700;
}

.pg-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.7rem 0.95rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: rgba(255, 255, 255, 0.35);
  color: var(--accent-strong);
  margin-bottom: 1.35rem;
}

.pg-pill::before {
  content: '';
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 999px;
  background: var(--accent);
  box-shadow: 0 0 0 0.35rem var(--accent-soft);
}

.pg-hero__copy h1 {
  font-size: clamp(3rem, 6vw, 5.4rem);
  line-height: 0.94;
  margin: 0;
  max-width: 11ch;
}

.pg-hero__subtitle,
.pg-section__header p,
.pg-feature-card p,
.pg-step-card p,
.pg-plan-card li,
.pg-cta-section p,
.pg-floating-card p,
.pg-stat-card span,
.pg-hero__note,
.pg-footer p {
  color: var(--muted);
}

.pg-hero__subtitle {
  max-width: 40rem;
  font-size: 1.08rem;
  line-height: 1.75;
  margin: 1.35rem 0 0;
}

.pg-hero__cta {
  gap: 0.85rem;
  flex-wrap: wrap;
  margin: 1.6rem 0 1rem;
}

.pg-hero__note {
  font-size: 0.94rem;
  margin: 0 0 2rem;
}

.pg-stats {
  gap: 0.95rem;
  flex-wrap: wrap;
}

.pg-stat-card {
  min-width: 12rem;
  flex: 1 1 12rem;
  padding: 1rem 1.05rem;
  border-radius: 1.25rem;
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow);
}

.pg-stat-card strong {
  display: block;
  font-size: 1.25rem;
  color: var(--text);
  margin-bottom: 0.4rem;
}

.pg-stat-card span,
.pg-feature-card p,
.pg-step-card p,
.pg-plan-card li,
.pg-cta-section p,
.pg-floating-card p,
.pg-footer p {
  line-height: 1.6;
}

.pg-hero__visual {
  position: relative;
  min-height: 30rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pg-hero__frame {
  width: min(100%, 42rem);
  padding: clamp(0.9rem, 2vw, 1.1rem);
  border-radius: 2rem;
  border: 1px solid var(--glass-stroke);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0.08));
  box-shadow: var(--hero-shadow);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

.pg-home[data-theme='dark'] .pg-hero__frame {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(17, 24, 35, 0.4));
}

.pg-hero__image {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 1.35rem;
  background: #ffffff;
  object-fit: cover;
}

.pg-floating-card {
  position: absolute;
  width: min(16.5rem, 42vw);
  padding: 1rem 1.05rem;
  border-radius: 1.15rem;
  border: 1px solid var(--border);
  background: var(--glass);
  box-shadow: var(--shadow);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  animation: floaty 5.4s ease-in-out infinite;
}

.pg-floating-card--left {
  left: -0.4rem;
  top: 3rem;
}

.pg-floating-card--right {
  right: -0.6rem;
  bottom: 2.5rem;
  animation-delay: 0.8s;
}

.pg-floating-card__label,
.pg-section__eyebrow,
.pg-feature-card__label,
.pg-plan-card__badge,
.pg-step-card__number {
  display: inline-block;
  color: var(--accent-strong);
  margin-bottom: 0.7rem;
}

.pg-section {
  padding-top: 4.5rem;
}

.pg-section__header {
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 1.8rem;
  max-width: 44rem;
}

.pg-section__header h2,
.pg-cta-section h2 {
  font-size: clamp(2.4rem, 4.5vw, 4rem);
  line-height: 0.95;
  margin: 0;
}

.pg-section__header p,
.pg-cta-section p {
  margin: 0;
  font-size: 1.02rem;
}

.pg-feature-grid,
.pg-step-grid,
.pg-plan-grid {
  display: grid;
  gap: 1rem;
}

.pg-feature-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pg-step-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pg-plan-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pg-feature-card,
.pg-step-card,
.pg-plan-card,
.pg-cta-section {
  border: 1px solid var(--border);
  background: var(--surface);
  box-shadow: var(--shadow);
}

.pg-feature-card,
.pg-step-card,
.pg-plan-card {
  border-radius: 1.6rem;
  padding: 1.35rem;
}

.pg-feature-card h3,
.pg-step-card h3,
.pg-plan-card h3 {
  margin: 0 0 0.8rem;
  font-size: 1.24rem;
  line-height: 1.2;
  color: var(--text);
}

.pg-feature-card p,
.pg-step-card p {
  margin: 0;
}

.pg-step-card__number {
  font-size: 0.84rem;
}

.pg-plan-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1.15rem;
}

.pg-plan-card.is-featured {
  background: linear-gradient(180deg, rgba(240, 187, 69, 0.14), var(--surface));
  border-color: var(--border-strong);
  transform: translateY(-0.2rem);
}

.pg-plan-card__badge {
  padding: 0.45rem 0.7rem;
  border-radius: 999px;
  background: var(--accent-soft);
  align-self: flex-start;
}

.pg-plan-card__price {
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
}

.pg-plan-card ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.65rem;
}

.pg-plan-card li {
  position: relative;
  padding-left: 1.2rem;
}

.pg-plan-card li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.62rem;
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 999px;
  background: var(--accent);
}

.pg-cta-section {
  margin-top: 4.5rem;
  border-radius: 2rem;
  padding: clamp(1.4rem, 4vw, 2rem);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
}

.pg-cta-section__content {
  max-width: 40rem;
}

.pg-footer {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.8rem;
  padding: 2.75rem 0 0;
}

.pg-footer__links {
  gap: 2rem;
  align-items: flex-start;
}

.pg-footer__links > div {
  display: grid;
  gap: 0.45rem;
}

.pg-footer__links span {
  font-size: 0.78rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--soft);
}

@keyframes floaty {
  0%,
  100% {
    transform: translate3d(0, 0, 0);
  }
  50% {
    transform: translate3d(0, -8px, 0);
  }
}

@media (max-width: 1100px) {
  .pg-header {
    border-radius: 1.5rem;
  }

  .pg-header__nav {
    display: none;
  }

  .pg-hero {
    grid-template-columns: 1fr;
  }

  .pg-hero__copy {
    order: 1;
  }

  .pg-hero__visual {
    order: 2;
    min-height: auto;
    padding-top: 1rem;
  }

  .pg-feature-grid,
  .pg-step-grid,
  .pg-plan-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .pg-cta-section,
  .pg-footer {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 760px) {
  .pg-home {
    padding-top: 0.7rem;
  }

  .pg-header,
  .pg-hero,
  .pg-section,
  .pg-cta-section,
  .pg-footer {
    width: min(100%, calc(100% - 1rem));
  }

  .pg-header {
    align-items: flex-start;
    padding: 0.85rem;
    border-radius: 1.35rem;
  }

  .pg-header__brand {
    flex: 1 1 auto;
    min-width: 0;
  }

  .pg-header__wordmark {
    font-size: 1.35rem;
  }

  .pg-header__actions {
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .pg-link-button--ghost {
    display: none;
  }

  .pg-hero {
    padding-top: 1.6rem;
  }

  .pg-hero__copy h1 {
    max-width: 10.6ch;
  }

  .pg-hero__subtitle {
    font-size: 1rem;
  }

  .pg-stat-card {
    min-width: 100%;
  }

  .pg-feature-grid,
  .pg-step-grid,
  .pg-plan-grid {
    grid-template-columns: 1fr;
  }

  .pg-floating-card {
    position: static;
    width: 100%;
    margin-top: 0.9rem;
    animation: none;
  }

  .pg-hero__visual {
    display: block;
  }

  .pg-hero__frame {
    width: 100%;
    border-radius: 1.4rem;
  }
}

@media (max-width: 520px) {
  .pg-header {
    gap: 0.8rem;
  }

  .pg-header__actions {
    width: 100%;
    justify-content: flex-end;
  }

  .pg-lang-toggle {
    order: 1;
  }

  .pg-theme-toggle {
    order: 2;
  }

  .pg-link-button--primary {
    order: 3;
    flex: 1 1 100%;
  }

  .pg-link-button--large,
  .pg-link-button--primary,
  .pg-link-button--outline {
    width: 100%;
  }

  .pg-hero__cta {
    flex-direction: column;
    align-items: stretch;
  }

  .pg-pill {
    width: 100%;
    justify-content: center;
    text-align: center;
  }

  .pg-section {
    padding-top: 3.6rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pg-link-button,
  .pg-theme-toggle,
  .pg-lang-toggle__button,
  .pg-floating-card {
    transition: none;
    animation: none;
  }
}
</style>