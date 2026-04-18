<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { getStoredTheme, setTheme } from '@/theme'
import { getStoredLang, t as _t } from '@/lang'
import { useLang } from '@/composables/useLang'
import Icon from '@/components/Icon.vue'

const props = defineProps<{
  titre?: string
}>()

const page = usePage()
const theme = ref(getStoredTheme())
const lang = useLang()
const sidebarOpen = ref(true)        // desktop: collapsed/expanded
const drawerOpen = ref(false)        // mobile: drawer ouvert/fermé

const primegestLogo = '/images/primegest.png'

const pageProps = computed(() => (page.props as any) ?? {})
const authUser = computed(() => pageProps.value.auth?.user || null)
const logoUrl = computed(() => pageProps.value.parametres?.logo_url || '')
const entrepriseName = computed(() => pageProps.value.parametres?.nom_entreprise || 'PrimeGest')
const succursaleName = computed(() => pageProps.value.succursale_name || pageProps.value.succursaleName || '')
const multiSuccursales = computed(() =>
  !!pageProps.value.parametres?.multi_succursales || !!pageProps.value.has_succursales
)
const isSuperAdmin = computed(() => {
  const role = String(authUser.value?.role || '').toLowerCase()
  return authUser.value?.is_super_admin === true || role === 'super_admin'
})
const userInitials = computed(() => {
  const name = authUser.value?.name || ''
  return name.split(' ').map((n: string) => n[0]).slice(0, 2).join('').toUpperCase() || '?'
})

function t(key: string): string {
  void lang.value
  return _t(key)
}

// ── Menu items ─────────────────────────────────────────
const menuItems = computed(() => {
  const currentPath = window.location.pathname
  const all = [
    { key: 'dashboard',         name: t('dashboard'),       icon: 'dashboard',            route: '/dashboard' },
    { key: 'ressources_humaines', name: t('human_resources'), icon: 'people',             route: '/ressources-humaines' },
    { key: 'caisse',            name: t('cashier'),           icon: 'payments',            route: '/caisse' },
    { key: 'mouvement_stocks',  name: t('stock_moves'),       icon: 'inventory',           route: '/mouvement-stocks' },
    { key: 'produits',          name: t('products'),          icon: 'store',               route: '/produits' },
    { key: 'tiers',             name: 'Tiers',                icon: 'contacts',            route: '/tiers' },
    { key: 'creances_dettes',   name: t('debts'),             icon: 'account_balance',     route: '/creances-dettes' },
    { key: 'journal',           name: t('journal'),           icon: 'book',                route: '/journals' },
    { key: 'rapports',          name: t('reports'),           icon: 'assessment',          route: '/rapport' },
    { key: 'archives',          name: t('archives'),          icon: 'archive',             route: '/archives' },
    ...(multiSuccursales.value && !succursaleName.value
      ? [{ key: 'succursales',  name: 'Succursales',          icon: 'store',               route: '/succursales' }]
      : []),
    ...(multiSuccursales.value
      ? [{ key: 'transferts',   name: 'Transferts',           icon: 'swap_horiz',          route: '/transferts' }]
      : []),
    { key: 'users',             name: t('users'),             icon: 'admin_panel_settings', route: '/users' },
    { key: 'parametres',        name: t('settings'),          icon: 'settings',            route: '/parametres' },
  ]

  const role = String(authUser.value?.role || '').toLowerCase()
  const isSuper = authUser.value?.is_super_admin === true || role === 'super_admin'
  const allowed = authUser.value?.access_pages || []

  const filtered = isSuper
    ? all
    : all.filter(i => i.key === 'dashboard' || allowed.includes(i.key))

  return filtered.map(i => ({
    ...i,
    active: currentPath === i.route || currentPath.startsWith(i.route + '/'),
  }))
})

// ── Actions ────────────────────────────────────────────
function navigate(route: string) {
  drawerOpen.value = false
  router.get(route)
}

function toggleTheme() {
  const next = theme.value === 'dark' ? 'light' : 'dark'
  theme.value = next
  setTheme(next)
}

function logout() {
  if (!confirm('Voulez-vous vraiment vous déconnecter ?')) return
  router.post('/logout')
}

// Fermer drawer si clic en dehors
function onOverlayClick() {
  drawerOpen.value = false
}

// Fermer drawer sur resize desktop
function onResize() {
  if (window.innerWidth >= 1024) drawerOpen.value = false
}

onMounted(() => window.addEventListener('resize', onResize))
onBeforeUnmount(() => window.removeEventListener('resize', onResize))
</script>

<template>
  <div class="flex min-h-screen" :class="theme === 'dark' ? 'bg-gray-900' : 'bg-gray-100'">

    <!-- ══════════════════════════════════════════
         OVERLAY MOBILE (fond sombre derrière drawer)
    ══════════════════════════════════════════ -->
    <Transition name="fade">
      <div
        v-if="drawerOpen"
        class="fixed inset-0 bg-black/60 z-40 lg:hidden"
        @click="onOverlayClick"
      />
    </Transition>

    <!-- ══════════════════════════════════════════
         SIDEBAR (desktop: fixe | mobile: drawer)
    ══════════════════════════════════════════ -->
    <aside
      :class="[
        'fixed h-screen flex flex-col transition-all duration-300 z-50 sidebar-bg',
        // Desktop : collapsed ou expanded
        'lg:translate-x-0',
        sidebarOpen ? 'lg:w-64' : 'lg:w-16',
        // Mobile : drawer slide-in depuis la gauche
        drawerOpen ? 'translate-x-0 w-72' : '-translate-x-full lg:translate-x-0',
      ]"
    >
      <!-- Logo + titre -->
      <div class="flex flex-col items-center pt-6 pb-4 px-3 border-b border-white/10">
        <div class="relative flex items-center justify-center mb-3">
          <img
            :src="primegestLogo"
            :class="[
              'rounded-full object-cover ring-2 ring-yellow-400/60 transition-all duration-300 shadow-lg',
              (sidebarOpen || drawerOpen) ? 'h-16 w-16' : 'h-10 w-10'
            ]"
          />
        </div>

        <Transition name="fade-slide">
          <div v-if="sidebarOpen || drawerOpen" class="text-center select-none">
            <div class="primgest-logo-text">
              <span class="text-gold font-display">Prime</span>
              <span class="text-white font-display">Gest</span>
            </div>
          </div>
        </Transition>

        <!-- Toggle sidebar (desktop uniquement) -->
        <button
          @click="sidebarOpen = !sidebarOpen"
          class="mt-3 text-white/60 hover:text-yellow-400 transition-colors hidden lg:block"
          :title="sidebarOpen ? 'Réduire' : 'Agrandir'"
        >
          <Icon :name="sidebarOpen ? 'menu_open' : 'menu'" />
        </button>

        <!-- Fermer drawer (mobile uniquement) -->
        <button
          @click="drawerOpen = false"
          class="mt-3 text-white/60 hover:text-yellow-400 transition-colors lg:hidden"
        >
          <Icon name="close" />
        </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto py-4 scrollbar-thin">
        <ul class="space-y-1 px-2">
          <li v-for="item in menuItems" :key="item.key">
            <button
              @click="navigate(item.route)"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 group relative"
              :class="[
                item.active
                  ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30'
                  : 'text-white/70 hover:bg-white/10 hover:text-white'
              ]"
            >
              <Icon :name="item.icon" class="shrink-0 text-lg" />
              <span v-if="sidebarOpen || drawerOpen" class="text-sm font-medium truncate text-left">
                {{ item.name }}
              </span>
              <!-- Tooltip sidebar collapsed desktop -->
              <div v-if="!sidebarOpen && !drawerOpen" class="sidebar-tooltip">{{ item.name }}</div>
            </button>
          </li>
        </ul>
      </nav>

      <!-- Actions bas -->
      <div class="p-3 border-t border-white/10 flex items-center justify-center gap-2">
        <button @click="toggleTheme" class="action-btn" :title="theme === 'dark' ? 'Mode clair' : 'Mode sombre'">
          <Icon :name="theme === 'dark' ? 'light_mode' : 'dark_mode'" />
        </button>
        <button @click="logout" class="action-btn action-btn--danger" title="Déconnexion">
          <Icon name="logout" />
        </button>
      </div>
    </aside>

    <!-- ══════════════════════════════════════════
         ZONE PRINCIPALE
    ══════════════════════════════════════════ -->
    <div
      :class="[
        'flex-1 flex flex-col transition-all duration-300 min-w-0',
        // Desktop : décale selon sidebar
        sidebarOpen ? 'lg:ml-64' : 'lg:ml-16',
        // Mobile : pas de décalage (drawer par-dessus)
        'ml-0',
      ]"
    >
      <!-- Topbar -->
      <header
        :class="[
          'sticky top-0 z-20 flex items-center px-4 py-3 shadow-sm border-b',
          theme === 'dark' ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'
        ]"
      >
        <!-- Bouton hamburger mobile -->
        <button
          @click="drawerOpen = true"
          class="lg:hidden mr-3 text-gray-500 hover:text-gray-800 dark:hover:text-white transition-colors"
          aria-label="Ouvrir le menu"
        >
          <Icon name="menu" class="text-2xl" />
        </button>

        <!-- Logo entreprise + nom -->
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <img v-if="logoUrl" :src="logoUrl" class="h-8 w-8 object-contain rounded shadow shrink-0" />
          <div class="min-w-0">
            <span
              :class="theme === 'dark' ? 'text-white' : 'text-gray-800'"
              class="font-semibold text-sm sm:text-base truncate block"
            >
              {{ entrepriseName }}
              <span v-if="succursaleName" class="text-yellow-500"> ({{ succursaleName }})</span>
            </span>
          </div>
        </div>

        <!-- Titre page (centré, masqué sur très petit écran) -->
        <div class="hidden sm:block flex-1 text-center">
          <span :class="theme === 'dark' ? 'text-gray-300' : 'text-gray-600'" class="text-sm font-medium">
            {{ props.titre || '' }}
          </span>
        </div>

        <!-- Avatar utilisateur -->
        <div class="flex items-center gap-2 flex-shrink-0">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-yellow-600 flex items-center justify-center text-black font-bold text-xs shadow">
            {{ userInitials }}
          </div>
          <div class="hidden md:block text-left">
            <div :class="theme === 'dark' ? 'text-white' : 'text-gray-800'" class="text-sm font-medium leading-none">
              {{ authUser?.name || '-' }}
            </div>
            <div class="text-xs text-gray-500 capitalize">{{ authUser?.role || '-' }}</div>
          </div>
        </div>
      </header>

      <!-- Bannière succursale -->
      <div v-if="succursaleName || isSuperAdmin" class="px-4 pt-3">
        <div
          :class="[
            'rounded-xl px-4 py-2 flex items-center justify-between border gap-2',
            theme === 'dark'
              ? 'bg-yellow-900/20 border-yellow-700/40 text-yellow-300'
              : 'bg-yellow-50 border-yellow-200 text-yellow-900'
          ]"
        >
          <div class="flex items-center gap-2 min-w-0">
            <Icon name="store" class="text-yellow-500 shrink-0" />
            <span class="font-semibold text-sm truncate">
              {{ entrepriseName }}<span v-if="succursaleName"> — {{ succursaleName }}</span>
            </span>
          </div>
          <button
            v-if="succursaleName && isSuperAdmin"
            @click="router.get('/succursales-exit')"
            class="text-xs bg-yellow-500 text-black px-3 py-1 rounded-lg hover:bg-yellow-400 font-medium transition-colors shrink-0"
          >
            ← Central
          </button>
        </div>
      </div>

      <!-- Contenu de la page -->
      <main class="flex-1 overflow-x-hidden">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&display=swap');

.sidebar-bg {
  background: linear-gradient(180deg, #0f0f0f 0%, #111827 60%, #0a0a0a 100%);
}
.font-display {
  font-family: 'Playfair Display', Georgia, serif;
  font-weight: 900;
  letter-spacing: -0.02em;
}
.primgest-logo-text { font-size: 1.4rem; line-height: 1; }
.text-gold {
  color: #D4AF37;
  text-shadow: 0 0 12px rgba(212,175,55,0.5);
}

.action-btn {
  width: 2.25rem; height: 2.25rem;
  display: flex; align-items: center; justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.7);
  transition: all 0.2s;
}
.action-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
.action-btn--danger:hover { background: #991b1b; border-color: #b91c1c; color: #fff; }

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

.scrollbar-thin::-webkit-scrollbar       { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.2s ease; }
.fade-slide-enter-from, .fade-slide-leave-to       { opacity: 0; transform: translateY(-4px); }
</style>
