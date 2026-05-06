<template>
  <div class="flex min-h-screen" :class="theme === 'dark' ? 'bg-gray-900' : 'bg-gray-100'">

    <!-- ═══════════════════════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════════════════════ -->
    <aside
      :class="[
        'fixed h-screen flex flex-col transition-all duration-300 z-30',
        sidebarOpen ? 'w-64' : 'w-16',
        'sidebar-bg'
      ]"
    >
      <!-- Logo + titre -->
      <div class="flex flex-col items-center pt-6 pb-4 px-3 border-b border-white/10">
        <div class="relative flex items-center justify-center mb-3">
          <img
            :src="primegestLogo"
            :class="[
              'rounded-full object-cover ring-2 ring-yellow-400/60 transition-all duration-300 shadow-lg shadow-yellow-500/20',
              sidebarOpen ? 'h-20 w-20' : 'h-10 w-10 ring-4 ring-yellow-400/80'
            ]"
          />
          <span v-if="sidebarOpen" class="absolute inset-0 rounded-full animate-pulse-ring"></span>
        </div>

        <transition name="fade-slide">
          <div v-if="sidebarOpen" class="text-center select-none">
            <div class="primgest-logo-text">
              <span class="text-gold font-display">Prime</span><span
                :class="theme === 'dark' ? 'text-white' : 'text-white'"
                class="font-display"
              >Gest</span>
            </div>
          </div>
        </transition>

        <button
          @click="sidebarOpen = !sidebarOpen"
          class="mt-3 text-white/60 hover:text-yellow-400 transition-colors"
        >
          <Icon :name="sidebarOpen ? 'menu_open' : 'menu'" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto py-4 scrollbar-thin">
        <ul class="space-y-1 px-2">
          <li v-for="item in menuItems" :key="item.name">
            <Link
              :href="item.route"
              class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 group"
              :class="[
                item.active
                  ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'
                  : 'text-white/70 hover:bg-white/10 hover:text-white'
              ]"
            >
              <Icon :name="item.icon" class="shrink-0 text-lg" />
              <span v-if="sidebarOpen" class="text-sm font-medium truncate">{{ item.name }}</span>
              <div v-if="!sidebarOpen" class="sidebar-tooltip">{{ item.name }}</div>
            </Link>
          </li>
        </ul>
      </nav>

      <!-- Actions bas de sidebar -->
      <div class="p-3 border-t border-white/10 flex items-center justify-center gap-2">
        <button
          @click="toggleTheme"
          class="action-btn"
          :title="theme === 'dark' ? 'Mode clair' : 'Mode sombre'"
        >
          <Icon :name="theme === 'dark' ? 'light_mode' : 'dark_mode'" />
        </button>
        <button @click="shareApp" class="action-btn" title="Partager">
          <Icon name="share" />
        </button>
        <button @click="logout" class="action-btn action-btn--danger" title="Déconnexion">
          <Icon name="logout" />
        </button>
      </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════
         CONTENU PRINCIPAL
    ═══════════════════════════════════════════════════ -->
    <div :class="['flex-1 flex flex-col transition-all duration-300', sidebarOpen ? 'ml-64' : 'ml-16']">

      <!-- Topbar -->
      <header
        :class="[
          'sticky top-0 z-20 flex items-center px-6 py-3 shadow-sm border-b',
          theme === 'dark'
            ? 'bg-gray-800 border-gray-700'
            : 'bg-white border-gray-200'
        ]"
      >
        <div class="w-1/3 flex items-center gap-3">
          <!-- Logo entreprise -->
          <img
            v-if="logoUrl"
            :src="logoUrl"
            class="h-10 w-10 object-contain rounded shadow"
          />
          <!-- Nom entreprise -->
          <span :class="theme === 'dark' ? 'text-white' : 'text-gray-800'" class="font-semibold text-lg">
            {{ entrepriseName }}
            <span v-if="props.succursaleName" class="text-yellow-500">
              ({{ props.succursaleName }})
            </span>
          </span>
        </div>

        <div class="w-1/3 text-center">
          <span :class="theme === 'dark' ? 'text-gray-300' : 'text-gray-600'" class="text-lg font-serif tracking-wide">
            {{ t('dashboard') }}
          </span>
        </div>

        <div class="w-1/3 flex justify-end items-center gap-3 relative">

          <button @click="toggleProfile" class="flex items-center gap-2 group">
            <div class="relative">
              <div class="w-9 h-9 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-black font-bold text-sm shadow">
                {{ userInitials }}
              </div>
              <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-gray-900"
                :class="{
                  'bg-red-500':    !offlineStore.isOnline,
                  'bg-yellow-400': offlineStore.isOnline && offlineStore.isSyncing,
                  'bg-green-500':  offlineStore.isOnline && !offlineStore.isSyncing,
                }"
              ></span>
            </div>
            <div class="hidden md:block text-left">
              <div :class="theme === 'dark' ? 'text-white' : 'text-gray-800'" class="text-sm font-medium leading-none">{{ authUser?.name || '-' }}</div>
              <div class="text-xs text-gray-500 capitalize">{{ authUser?.role || '-' }}</div>
            </div>
          </button>

          <!-- Dropdown profil -->
          <transition name="fade-slide">
            <div
              v-if="profileOpen"
              :class="[
                'absolute right-0 top-14 shadow-xl rounded-xl p-4 w-64 z-30 border',
                theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-100'
              ]"
            >
              <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-black font-bold">
                  {{ userInitials }}
                </div>
                <div>
                  <div :class="theme === 'dark' ? 'text-white' : 'text-gray-800'" class="font-semibold text-sm">{{ authUser?.name || '-' }}</div>
                  <div class="text-xs text-gray-500">{{ authUser?.email || '-' }}</div>
                  <div class="text-xs text-yellow-500 capitalize font-medium">{{ authUser?.role || '-' }}</div>
                </div>
              </div>
              <!-- Voyant statut -->
              <div class="flex items-center gap-2 mb-3">
                <span class="w-2.5 h-2.5 rounded-full"
                  :class="{
                    'bg-red-500':    !offlineStore.isOnline,
                    'bg-yellow-400': offlineStore.isOnline && offlineStore.isSyncing,
                    'bg-green-500':  offlineStore.isOnline && !offlineStore.isSyncing,
                  }"
                ></span>
                <span class="text-xs"
                  :class="{
                    'text-red-400':    !offlineStore.isOnline,
                    'text-yellow-400': offlineStore.isOnline && offlineStore.isSyncing,
                    'text-green-500':  offlineStore.isOnline && !offlineStore.isSyncing,
                  }"
                >
                  {{ !offlineStore.isOnline ? 'Hors connexion' : offlineStore.isSyncing ? 'Synchronisation...' : 'En ligne' }}
                </span>
                <span v-if="offlineStore.hasPending" class="text-xs text-amber-400 ml-auto">
                  {{ offlineStore.pendingCount }} en attente
                </span>
              </div>
              <div :class="theme === 'dark' ? 'border-gray-700 text-gray-400' : 'border-gray-100 text-gray-600'" class="border-t pt-3 text-xs space-y-1">
                <div>📞 {{ authUser?.employe?.telephone || '-' }}</div>
                <div>💼 {{ authUser?.employe?.poste || '-' }}</div>
              </div>
            </div>
          </transition>
        </div>
      </header>

      <!-- ✅ Badge plan — entre topbar et bannière, aligné à droite -->
      <div class="px-6 pt-3 flex items-center justify-end gap-2">
        <div :class="{
          'bg-gray-100 border-gray-200 text-gray-500':                                     currentPlan === 'free',
          'bg-blue-50 border-blue-200 text-blue-700':                                      currentPlan === 'premium',
          'bg-gradient-to-r from-purple-50 to-pink-50 border-purple-200 text-purple-700': currentPlan === 'pro',
        }" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-xs font-bold uppercase tracking-wide shadow-sm">
          <span v-if="currentPlan === 'free'">○</span>
          <span v-else-if="currentPlan === 'premium'" class="text-yellow-500">★</span>
          <span v-else class="text-purple-500">✦</span>
          {{ currentPlan }}
        </div>
        <button v-if="currentPlan === 'free'"
          @click="router.get('/upgrade')"
          class="text-xs bg-gradient-to-r from-yellow-400 to-yellow-500 text-black px-3 py-1 rounded-full font-semibold hover:from-yellow-300 hover:to-yellow-400 transition-all shadow-sm">
          Passer Premium →
        </button>
      </div>

      <!-- Bannière succursale -->
      <div v-if="props.succursaleName || isSuperAdmin" class="px-6 pt-3">
        <div
          :class="[
            'rounded-xl px-4 py-3 flex items-center justify-between border',
            theme === 'dark'
              ? 'bg-yellow-900/20 border-yellow-700/40 text-yellow-300'
              : 'bg-yellow-50 border-yellow-200 text-yellow-900'
          ]"
        >
          <div class="flex items-center gap-2">
            <Icon name="store" class="text-yellow-500" />
            <span class="font-semibold">
              {{ entrepriseName }}<span v-if="props.succursaleName"> — {{ props.succursaleName }}</span>
            </span>
          </div>
          <!-- ✅ Bouton Dashboard central — visible uniquement si succursale active + super admin -->
          <button
            v-if="props.succursaleName && isSuperAdmin"
            type="button"
            @click="router.get('/succursales-exit')"
            class="text-sm bg-yellow-500 text-black px-3 py-1 rounded-lg hover:bg-yellow-400 font-medium transition-colors"
          >
            ← Dashboard central
          </button>
        </div>
      </div>

      <!-- ═══ MAIN CONTENT ═══ -->
      <main class="p-6 flex-1 overflow-y-auto">

        <!-- Bannière hors-ligne -->
        <div v-if="!offlineStore.isOnline" class="mb-5 px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
          Mode hors-ligne — KPIs calculés depuis la base locale
        </div>

        <div class="grid grid-cols-12 gap-5">

          <!-- ── KPI Cards ── -->
          <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div :class="cardClass" class="kpi-card">
              <div class="kpi-icon bg-blue-100">
                <Icon name="inventory" class="text-blue-600" />
              </div>
              <div>
                <div class="kpi-label">{{ t('total_stock') }}</div>
                <div class="kpi-value">{{ formatCurrency(displayTotalStock) }}</div>
                <div class="kpi-sub">{{ t('stock_value') }}</div>
              </div>
            </div>
          </div>

          <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div :class="cardClass" class="kpi-card">
              <div class="kpi-icon bg-green-100">
                <Icon name="payments" class="text-green-600" />
              </div>
              <div>
                <div class="kpi-label">{{ t('total_sales') }}</div>
                <div class="kpi-value text-green-600">{{ formatCurrency(displayTotalVentes) }}</div>
                <div class="kpi-sub">{{ t('sales_recorded') }}</div>
              </div>
            </div>
          </div>

          <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div :class="cardClass" class="kpi-card">
              <div class="kpi-icon bg-red-100">
                <Icon name="money_off" class="text-red-500" />
              </div>
              <div>
                <div class="kpi-label">{{ t('total_expenses') }}</div>
                <div class="kpi-value text-red-500">{{ formatCurrency(displayTotalDepenses) }}</div>
                <div class="kpi-sub">{{ t('cash_out') }}</div>
              </div>
            </div>
          </div>

          <div class="col-span-12 lg:col-span-6 xl:col-span-3">
            <div :class="cardClass" class="kpi-card">
              <div class="kpi-icon bg-amber-100">
                <Icon name="report" class="text-amber-500" />
              </div>
              <div>
                <div class="kpi-label">{{ t('stock_alerts') }}</div>
                <div class="kpi-value text-amber-500">{{ displayAlertesStock.length }}</div>
                <div class="kpi-sub">{{ t('stock_alerts_low') }}</div>
              </div>
            </div>
          </div>

          <!-- ── ACTIVITÉS (GAUCHE LARGE) ── -->
          <div class="col-span-12 xl:col-span-6">
            <div :class="cardClass" class="rounded-xl p-5 h-full">
              <div class="flex items-center justify-between mb-4">
                <h2 :class="headingClass">{{ t('recent_activity') }}</h2>
                <button @click="goTo('/journals')" class="text-xs text-yellow-500 hover:text-yellow-400 font-medium">
                  {{ t('view_journal') }} →
                </button>
              </div>

              <div v-if="displayActivities?.length" class="space-y-3">
                <div
                  v-for="(act, idx) in displayActivities"
                  :key="idx"
                  :class="['flex items-start justify-between pb-2 border-b last:border-0', theme === 'dark' ? 'border-gray-700' : 'border-gray-100']"
                >
                  <div>
                    <div :class="theme === 'dark' ? 'text-gray-200' : 'text-gray-800'" class="text-sm font-medium">
                      {{ act.description }}
                    </div>
                    <div class="text-xs text-gray-500">Par {{ act.user }}</div>
                  </div>
                  <div class="text-xs text-gray-400 shrink-0 ml-2">{{ act.time }}</div>
                </div>
              </div>

              <div v-else class="text-sm text-gray-400">{{ t('no_activity') }}</div>
            </div>
          </div>

          <!-- ── COLONNE DROITE (STRUCTURÉE) ── -->
          <div class="col-span-12 xl:col-span-6 flex flex-col gap-5">

            <!-- 🔸 ALERTES STOCK -->
            <div :class="cardClass" class="rounded-xl p-5 flex-1">
              <h2 :class="headingClass" class="mb-4 text-center">
                {{ t('stock_alerts_low') }}
              </h2>

              <div v-if="displayAlertesStock.length" class="space-y-2 flex flex-col items-center">
                <div
                  v-for="(a, idx) in displayAlertesStock"
                  :key="idx"
                  :class="[
                    'flex items-center justify-between text-sm px-3 py-2 rounded-lg w-full max-w-md',
                    theme === 'dark' ? 'bg-amber-900/20' : 'bg-amber-50'
                  ]"
                >
                  <div class="flex flex-col">
                    <span :class="theme === 'dark' ? 'text-amber-300' : 'text-amber-800'" class="font-medium">
                      {{ a.produit }}
                    </span>
                    <span v-if="a.succursale" class="text-xs text-gray-400 mt-0.5">
                      📍 {{ a.succursale }}
                    </span>
                  </div>
                  <span class="text-gray-500 text-xs shrink-0 ml-2">
                    {{ a.quantite }} / {{ t('threshold') ?? 'seuil' }} {{ a.seuil }}
                  </span>
                </div>
              </div>

              <div v-else class="text-sm text-gray-400 text-center">
                {{ t('no_alerts') }}
              </div>
            </div>

            <!-- 🔸 RAPPORT -->
            <div :class="cardClass" class="rounded-xl p-4 flex items-center justify-center">
              <button
                @click="goTo('/rapport')"
                class="bg-gradient-to-r from-yellow-500 to-yellow-400 text-black font-semibold px-6 py-2 rounded-lg hover:from-yellow-400 hover:to-yellow-300 transition-all shadow-md shadow-yellow-500/30 w-full max-w-xs"
              >
                {{ t('reports') }}
              </button>
            </div>

          </div>

          <!-- ── Graphique ventes/achats ── -->
          <div class="col-span-12 lg:col-span-6">
            <div :class="cardClass" class="rounded-xl p-5">
              <div class="flex items-center justify-between mb-4">
                <h2 :class="headingClass">{{ chartTitle }}</h2>
                <div class="flex items-center gap-3">
                  <label class="text-sm text-gray-500">{{ t('chart_type') }}</label>
                  <select
                    v-model="chartType"
                    :class="[
                      'border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-yellow-400 outline-none',
                      theme === 'dark' ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-200 text-gray-700'
                    ]"
                  >
                    <option value="daily">{{ t('chart_daily') }}</option>
                    <option value="monthly">{{ t('chart_monthly') }}</option>
                  </select>
                </div>
              </div>
              <div class="h-64">
                <canvas ref="salesChartEl"></canvas>
              </div>
            </div>
          </div>

          <!-- ── Diagramme circulaire succursales ── -->
          <div
            v-if="isSuperAdmin && multiSuccursales && !props.succursaleName && succursaleChartData.labels.length > 0"
            class="col-span-12 lg:col-span-6"
          >
            <div :class="cardClass" class="rounded-xl p-5">
              <div class="flex items-center gap-2 mb-1">
                <Icon name="donut_large" class="text-yellow-500" />
                <h2 :class="headingClass">Ventes par succursale</h2>
              </div>
              <p class="text-xs text-gray-400 mb-4">Vue consolidée — Dashboard central uniquement</p>
              <div class="flex flex-col md:flex-row items-center gap-6">
                <div class="relative w-64 h-64 shrink-0">
                  <canvas ref="pieChartEl"></canvas>
                  <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <div class="text-xs text-gray-400">Total</div>
                    <div :class="theme === 'dark' ? 'text-white' : 'text-gray-800'" class="font-bold text-sm">
                      {{ formatCurrency(succursaleChartData.values.reduce((a, b) => a + b, 0)) }}
                    </div>
                  </div>
                </div>
                <div class="flex-1 space-y-2">
                  <div
                    v-for="(label, i) in succursaleChartData.labels"
                    :key="i"
                    class="flex items-center justify-between text-sm"
                  >
                    <div class="flex items-center gap-2">
                      <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: pieColors[i % pieColors.length] }"></span>
                      <span :class="theme === 'dark' ? 'text-gray-300' : 'text-gray-700'">{{ label }}</span>
                    </div>
                    <span :class="theme === 'dark' ? 'text-gray-400' : 'text-gray-500'" class="font-mono text-xs">
                      {{ formatCurrency(succursaleChartData.values[i]) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Top produits ── -->
          <div
            :class="[
              'ccol-span-12 flex justify-center',
              isSuperAdmin && multiSuccursales && !props.succursaleName && succursaleChartData.labels.length > 0
                ? 'lg:col-span-12'
                : 'col-span-12'
            ]"
          >
            <div :class="cardClass" class="rounded-xl p-5 h-full w-full max-w-2xl">
              <div class="flex items-center justify-between mb-4">
                <h2 :class="headingClass">{{ t('best_sales') }}</h2>
                <button @click="goTo('/rapport')" class="text-xs text-yellow-500 hover:text-yellow-400 font-medium">
                  {{ t('view_report') }} →
                </button>
              </div>
              <div v-if="topProduits?.length" class="space-y-2">
                <div
                  v-for="(p, idx) in topProduits"
                  :key="idx"
                  :class="['flex items-center gap-3 px-3 py-2 rounded-lg', theme === 'dark' ? 'hover:bg-gray-700' : 'hover:bg-gray-50']"
                >
                  <span class="w-6 h-6 rounded-full bg-yellow-500/20 text-yellow-600 text-xs flex items-center justify-center font-bold shrink-0">
                    {{ idx + 1 }}
                  </span>
                  <span :class="theme === 'dark' ? 'text-gray-200' : 'text-gray-800'" class="flex-1 text-sm truncate">{{ p.nom }}</span>
                  <span class="text-gray-400 text-xs tabular-nums">{{ p.quantite }} u</span>
                  <span :class="theme === 'dark' ? 'text-yellow-400' : 'text-yellow-600'" class="text-sm font-semibold tabular-nums">{{ formatCurrency(p.montant) }}</span>
                </div>
              </div>
              <div v-else class="text-sm text-gray-400">{{ t('no_sales') }}</div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useOfflineStore } from '@/stores/useOfflineStore'
import { useLocalDB } from '@/composables/useLocalDB'
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import { getStoredTheme, setTheme } from '@/theme'
import { getStoredLang, t as _t } from '@/lang'
import { Chart, BarController, BarElement, LineController, LineElement, PointElement, ArcElement, PieController, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
Chart.register(BarController, BarElement, LineController, LineElement, PointElement, ArcElement, PieController, CategoryScale, LinearScale, Tooltip, Legend)
import Icon from '@/components/Icon.vue'

interface Activity {
  id: number
  description: string
  type: string
  time: string
  occurred_at?: string
  user: string
}

interface TopProduit {
  nom: string
  quantite: number
  montant: number
}

const props = defineProps<{
  totalStock?: number
  totalVentes?: number
  totalDepenses?: number
  authUser?: { name?: string; email?: string; role?: string; employe?: { telephone?: string; poste?: string } }
  recentActivities?: Activity[]
  topProduits?: TopProduit[]
  devise?: string
  alertesStock?: { produit: string; quantite: number; seuil: number }[]
  monthlyChart?: { labels: string[]; sales: number[]; purchases: number[]; year: number }
  dailyChart?: { labels: string[]; sales: number[]; purchases: number[]; month: string; year: number }
  entrepriseName?: string
  succursaleName?: string
  logoUrl?: string
  parametres?: { multi_succursales?: boolean }
  has_succursales?: boolean
  succursaleChart?: { labels: string[]; values: number[] }
}>()

// ── State ──────────────────────────────────────────────
const sidebarOpen = ref(true)
const page = usePage()
const primegestLogo = '/images/primegest.png'

const pageProps = computed(() => (page.props as any) ?? {})
const logoUrl    = computed(() => props.logoUrl || pageProps.value.parametres?.logo_url || '')
const authUser   = computed(() => props.authUser || pageProps.value.auth?.user || null)

const multiSuccursales = computed(() =>
  !!props.parametres?.multi_succursales ||
  !!props.has_succursales ||
  !!pageProps.value.parametres?.multi_succursales ||
  !!pageProps.value.has_succursales
)

const isSuperAdmin = computed(() => {
  const role = String((authUser.value as any)?.role || '').toLowerCase()
  return (authUser.value as any)?.is_super_admin === true || role === 'super_admin'
})

// ✅ Plan — plus de planExpiringSoon ni planExpiresAt affiché
const currentPlan = computed(() => pageProps.value.plan ?? 'free')

const userInitials = computed(() => {
  const name = authUser.value?.name || ''
  return name.split(' ').map((n: string) => n[0]).slice(0, 2).join('').toUpperCase() || '?'
})

const profileOpen = ref(false)
const offlineStore = useOfflineStore()
const localDB      = useLocalDB()
const theme       = ref(getStoredTheme())

// ── KPI locaux (mode hors-ligne) ──────────────────────────
const localTotalStock    = ref(0)
const localTotalVentes   = ref(0)
const localTotalDepenses = ref(0)
const localAlertesStock  = ref<{ produit: string; quantite: number; seuil: number }[]>([])

async function loadLocalKPIs() {
    if (!localDB.isAvailable) return
    const valeur = await localDB.getValeurStock()
    localTotalStock.value = valeur.valeur_stock ?? 0
    const journalSums = await localDB.getJournalSumByType()
    localTotalVentes.value = journalSums.entree ?? 0
    localTotalDepenses.value = journalSums.sortie ?? 0
    const produits = await localDB.getProduits()
    localAlertesStock.value = produits
        .filter((p) => p.quantite <= (p as any).seuil_stock && (p as any).seuil_stock > 0)
        .map((p) => ({ produit: p.nom_produit, quantite: p.quantite, seuil: (p as any).seuil_stock ?? 0 }))
}

const displayTotalStock    = computed(() => offlineStore.isOnline ? (props.totalStock    ?? 0) : localTotalStock.value)
const displayTotalVentes   = computed(() => offlineStore.isOnline ? (props.totalVentes   ?? 0) : localTotalVentes.value)
const displayTotalDepenses = computed(() => offlineStore.isOnline ? (props.totalDepenses ?? 0) : localTotalDepenses.value)
const displayAlertesStock  = computed(() => offlineStore.isOnline ? (props.alertesStock  ?? []) : localAlertesStock.value)
const lang        = ref(getStoredLang())
const chartType   = ref<'daily' | 'monthly'>('daily')
const nowTick     = ref(Date.now())

// ── Couleurs chart succursales ──────────────────────────
const pieColors = [
  '#F59E0B', '#3B82F6', '#10B981', '#EF4444', '#8B5CF6',
  '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'
]

const succursaleChartData = computed(() => ({
  labels: props.succursaleChart?.labels || [],
  values: props.succursaleChart?.values || [],
}))

// ── Computed classes ───────────────────────────────────
const cardClass = computed(() =>
  theme.value === 'dark'
    ? 'bg-gray-800 border border-gray-700 shadow-sm'
    : 'bg-white border border-gray-100 shadow-sm'
)
const headingClass = computed(() =>
  theme.value === 'dark' ? 'text-white font-semibold text-base' : 'text-gray-800 font-semibold text-base'
)
const chartTitle = computed(() =>
  chartType.value === 'daily' ? t('chart_daily') : t('chart_monthly')
)

// ── Menu ───────────────────────────────────────────────
const menuItems = computed(() => {
  const all = [
    { key: 'ressources_humaines', name: t('human_resources'), icon: 'people', route: '/ressources-humaines', active: false },
    { key: 'caisse', name: t('cashier'), icon: 'payments', route: '/caisse', active: false },
    { key: 'mouvement_stocks', name: t('stock_moves'), icon: 'inventory', route: '/mouvement-stocks', active: false },
    { key: 'produits', name: t('products'), icon: 'store', route: '/produits', active: false },
    { key: 'tiers', name: 'Tiers', icon: 'people', route: '/tiers', active: false },
    { key: 'creances_dettes', name: t('debts'), icon: 'account_balance', route: '/creances-dettes', active: false },
    { key: 'journal', name: t('journal'), icon: 'book', route: '/journals', active: false },
    { key: 'rapports', name: t('reports'), icon: 'assessment', route: '/rapport', active: false },
    { key: 'archives', name: t('archives'), icon: 'archive', route: '/archives', active: false },
    ...(multiSuccursales.value && !props.succursaleName
      ? [{ key: 'succursales', name: 'Succursales', icon: 'store', route: '/succursales', active: false }]
      : []),
    ...(multiSuccursales.value
      ? [{ key: 'transferts', name: 'Transferts', icon: 'swap_horiz', route: '/transferts', active: false }]
      : []),
    { key: 'users', name: t('users'), icon: 'admin_panel_settings', route: '/users', active: false },
    { key: 'parametres', name: t('settings'), icon: 'settings', route: '/parametres', active: false },
  ]
  const role    = String((authUser.value as any)?.role || '').toLowerCase()
  const isSuper = (authUser.value as any)?.is_super_admin === true || role === 'super_admin'
  if (isSuper) return all
  const allowed = (authUser.value as any)?.access_pages || []
  if (!allowed.length) return all.filter(i => i.key === 'dashboard')
  return all.filter(i => i.key === 'dashboard' || allowed.includes(i.key))
})

// ── Formatters ─────────────────────────────────────────
function formatCurrency(value: number | undefined): string {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: props.devise || 'CDF',
    minimumFractionDigits: 0,
  }).format(value || 0)
}

function goTo(route: string) { router.get(route) }
function toggleProfile()     { profileOpen.value = !profileOpen.value }

async function shareApp() {
  const url = window.location.origin
  if (navigator.share) { try { await navigator.share({ title: 'PrimeGest', url }); return } catch {} }
  if (navigator.clipboard) { await navigator.clipboard.writeText(url); alert('Lien copié.') }
  else alert(url)
}

function logout() {
  if (!confirm('Voulez-vous vraiment vous déconnecter ?')) return
  router.post('/logout')
}

function toggleTheme() {
  const next = theme.value === 'dark' ? 'light' : 'dark'
  theme.value = next
  setTheme(next)
}

function t(key: string): string {
  void lang.value
  return _t(key)
}

function syncLang() { lang.value = getStoredLang() }

// ── Charts ─────────────────────────────────────────────
const salesChartEl = ref<HTMLCanvasElement | null>(null)
const pieChartEl   = ref<HTMLCanvasElement | null>(null)
let salesChart: Chart | null = null
let pieChart:   Chart | null = null

function buildSalesChart() {
  if (!salesChartEl.value) return
  if (salesChart) { salesChart.destroy(); salesChart = null }
  const data   = chartType.value === 'daily' ? props.dailyChart : props.monthlyChart
  const isDark = theme.value === 'dark'
  salesChart   = new Chart(salesChartEl.value, {
    type: 'line',
    data: {
      labels: data?.labels || [],
      datasets: [
        {
          label: t('sales_label'),
          data: data?.sales || [],
          borderColor: '#F59E0B',
          backgroundColor: 'rgba(245,158,11,0.10)',
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#F59E0B',
          pointRadius: 3,
        },
        {
          label: t('purchases_label'),
          data: data?.purchases || [],
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59,130,246,0.08)',
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#3B82F6',
          pointRadius: 3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: 'bottom',
          labels: { color: isDark ? '#9CA3AF' : '#6B7280', font: { size: 12 } },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: isDark ? '#6B7280' : '#9CA3AF' } },
        y: { beginAtZero: true, grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' }, ticks: { color: isDark ? '#6B7280' : '#9CA3AF' } },
      },
    },
  })
}

function buildPieChart() {
  if (!pieChartEl.value) return
  if (pieChart) { pieChart.destroy(); pieChart = null }
  const { labels, values } = succursaleChartData.value
  if (!labels.length) return
  pieChart = new Chart(pieChartEl.value, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: pieColors.slice(0, labels.length),
        borderWidth: 2,
        borderColor: theme.value === 'dark' ? '#1F2937' : '#FFFFFF',
        hoverOffset: 8,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.label}: ${formatCurrency(ctx.parsed)}`,
          },
        },
      },
    },
  })
}

// ── Lifecycle ──────────────────────────────────────────
onMounted(async () => {
  buildSalesChart()
  nextTick(() => buildPieChart())
  window.addEventListener('primegest:lang', syncLang)
  if (!offlineStore.isOnline) await loadLocalKPIs()
  window.addEventListener('primegest:sync-pulled', loadLocalKPIs)
})

onBeforeUnmount(() => {
  salesChart?.destroy()
  pieChart?.destroy()
  window.removeEventListener('primegest:lang', syncLang)
})

watch(
  () => [props.monthlyChart, props.dailyChart, chartType.value, lang.value, theme.value],
  () => buildSalesChart(),
  { deep: true }
)

watch(
  () => [props.succursaleChart, theme.value],
  () => nextTick(() => buildPieChart()),
  { deep: true }
)

// ── Activités ──────────────────────────────────────────
const displayActivities = computed(() =>
  (props.recentActivities || []).map((a) => ({
    ...a,
    time: formatRelativeTime(
      Math.min(a.occurred_at ? new Date(a.occurred_at).getTime() : Date.now(), nowTick.value),
      nowTick.value,
      lang.value
    ),
  }))
)

function formatRelativeTime(occurred: number, now: number, locale: string) {
  const diff = Math.max(1, Math.floor((now - occurred) / 1000))
  const rtf  = new Intl.RelativeTimeFormat(locale === 'en' ? 'en' : 'fr', { numeric: 'always' })
  if (diff < 60)   return rtf.format(-diff, 'second')
  if (diff < 3600) return rtf.format(-Math.floor(diff / 60), 'minute')
  if (diff < 86400) return rtf.format(-Math.floor(diff / 3600), 'hour')
  return rtf.format(-Math.floor(diff / 86400), 'day')
}

let tickTimer: number | null = null
let refreshTimer: number | null = null

onMounted(() => {
  tickTimer    = window.setInterval(() => { nowTick.value = Date.now() }, 1000)
  refreshTimer = window.setInterval(() => {
    router.reload({ only: ['recentActivities'], preserveState: true, preserveScroll: true })
  }, 10000)
})

onBeforeUnmount(() => {
  if (tickTimer)    window.clearInterval(tickTimer)
  if (refreshTimer) window.clearInterval(refreshTimer)
})
</script>

<style scoped>

/* ── Sidebar background ── */
.sidebar-bg {
  background: linear-gradient(180deg, #0f0f0f 0%, #111827 60%, #0a0a0a 100%);
}

/* ── Logo PrimeGest bicolore + typo distinctive ── */
.font-display {
  font-family: 'Playfair Display', Georgia, serif;
  font-weight: 900;
  letter-spacing: -0.02em;
}
.primgest-logo-text {
  font-size: 1.5rem;
  line-height: 1;
}
.text-gold {
  color: #D4AF37;
  text-shadow: 0 0 12px rgba(212, 175, 55, 0.5);
}

/* ── Halo animé autour du logo ── */
@keyframes pulse-ring {
  0%   { box-shadow: 0 0 0 0 rgba(212,175,55,0.4); }
  70%  { box-shadow: 0 0 0 10px rgba(212,175,55,0); }
  100% { box-shadow: 0 0 0 0 rgba(212,175,55,0); }
}
.animate-pulse-ring {
  animation: pulse-ring 2.5s ease-out infinite;
  border-radius: 9999px;
}

/* ── Boutons action sidebar ── */
.action-btn {
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.7);
  transition: all 0.2s;
}
.action-btn:hover {
  background: rgba(255,255,255,0.1);
  color: #fff;
}
.action-btn--danger:hover {
  background: #991b1b;
  border-color: #b91c1c;
  color: #fff;
}

/* ── Tooltip menu sidebar fermé ── */
.sidebar-tooltip {
  display: none;
  position: absolute;
  left: 100%;
  top: 50%;
  transform: translateY(-50%);
  margin-left: 0.5rem;
  background: #1f2937;
  color: #fff;
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  white-space: nowrap;
  pointer-events: none;
  z-index: 50;
}
.group:hover .sidebar-tooltip { display: block; }

/* ── KPI Cards ── */
.kpi-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  border-radius: 0.75rem;
}
.kpi-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-label {
  font-size: 0.8rem;
  color: #6B7280;
  font-weight: 500;
  margin-bottom: 0.25rem;
}
.kpi-value {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.2;
}
.kpi-sub {
  font-size: 0.7rem;
  color: #9CA3AF;
  margin-top: 0.25rem;
}

/* ── Transitions ── */
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.2s ease; }
.fade-slide-enter-from, .fade-slide-leave-to       { opacity: 0; transform: translateY(-6px); }

/* ── Scrollbar sidebar ── */
.scrollbar-thin::-webkit-scrollbar       { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }
</style>



