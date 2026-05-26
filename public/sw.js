const SHELL_CACHE   = 'primegest-shell-v10'
const INERTIA_CACHE = 'primegest-inertia-v12'
const ASSET_CACHE   = 'primegest-assets-v9'

// Pages offline-first uniquement — rapport, users, parametres, archives restent online-only
const INERTIA_ROUTES = [
  '/dashboard',
  '/entreprises',
  '/produits',
  '/mouvement-stocks',
  '/caisse',
  '/journals',
  '/tiers',
  '/creances-dettes',
  '/transferts',
  '/succursales',
]

// Map URL → composant Inertia + props vides pour le fallback hors-ligne.
// Ordre important : routes spécifiques avant les génériques.
// Les pages chargent leur data depuis Dexie via onMounted quand offlineStore.isOnline === false.
const OFFLINE_ROUTES = [
  { re: /^\/dashboard$/,              component: 'Dashboard',             props: {} },
  { re: /^\/produits/,                component: 'Produits/Index',        props: { produits: [] } },
  { re: /^\/mouvement-stocks/,        component: 'MouvementStock/Index',  props: { mouvements: [], produits: [] } },
  { re: /^\/caisse/,                  component: 'Caisse/Index',          props: { caisses: [], devise: 'USD', hasInitial: false } },
  { re: /^\/journals/,                component: 'Journal/Index',         props: { journals: [] } },
  { re: /^\/tiers/,                   component: 'Tiers/Index',           props: { clients: [], fournisseurs: [] } },
  { re: /^\/creances-dettes\/[^/]+/,  component: 'CreancesDettes/Detail', props: { creance: null } },
  { re: /^\/creances-dettes/,         component: 'CreancesDettes/Index',  props: { creances: [], dettes: [] } },
  { re: /^\/transferts/,              component: 'Transferts/Index',      props: { transferts: [], succursales: [] } },
  { re: /^\/succursales\/[^/]+/,      component: 'Succursales/Show',      props: { succursale: {} } },
  { re: /^\/succursales/,             component: 'Succursales/Index',     props: { succursales: [] } },
]

function getOfflinePage(pathname) {
  const match = OFFLINE_ROUTES.find(({ re }) => re.test(pathname))
  return match ? { component: match.component, props: match.props } : null
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    Promise.all([
      caches.open(SHELL_CACHE).then((cache) =>
        Promise.allSettled([
          cache.add('/').catch(() => {}),
          cache.add('/offline.html').catch(() => {}),
        ])
      ),
      precacheViteAssets(),
    ])
  )
  self.skipWaiting()
})

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting()
  }
  if (event.data?.type === 'PRECACHE_INERTIA') {
    event.waitUntil(
      precacheInertiaRoutes(event.data.routes || INERTIA_ROUTES, event.data.version || '')
    )
  }
})

self.addEventListener('activate', (event) => {
  const VALID = [SHELL_CACHE, INERTIA_CACHE, ASSET_CACHE]
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => !VALID.includes(k)).map((k) => caches.delete(k))
      )
    )
  )
  self.clients.claim()
})

self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  if (url.origin !== self.location.origin) return

  // API GET — NetworkFirst avec timeout 3s, fallback offline JSON
  if (url.pathname.startsWith('/api/') && request.method === 'GET') {
    event.respondWith(apiNetworkFirst(request))
    return
  }

  // Mutations API — laisser passer (gérées par useOfflineQueue côté app)
  if (url.pathname.startsWith('/api/')) return

  if (request.method !== 'GET') return

  const isAsset = /\.(js|css|png|jpg|jpeg|svg|ico|woff2?|ttf)(\?.*)?$/.test(url.pathname)
  const isInertia = request.headers.get('X-Inertia') === 'true'
  const isInertiaRoute = INERTIA_ROUTES.some(
    (r) => url.pathname === r || url.pathname.startsWith(r + '/')
  )

  if (isAsset) {
    event.respondWith(cacheFirst(request))
    return
  }

  if (isInertia && isInertiaRoute) {
    event.respondWith(inertiaNetworkFirst(request))
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(navigateFallback(request))
    return
  }
})

// Pré-cache tous les chunks JS/CSS depuis le manifest Vite au démarrage du SW
async function precacheViteAssets() {
  try {
    const res = await fetch('/build/manifest.json')
    if (!res.ok) return
    const manifest = await res.json()
    const cache = await caches.open(ASSET_CACHE)

    const urls = []
    for (const entry of Object.values(manifest)) {
      if (entry.file) urls.push(`/build/${entry.file}`)
      for (const css of (entry.css || [])) urls.push(`/build/${css}`)
      for (const asset of (entry.assets || [])) urls.push(`/build/${asset}`)
    }

    for (let i = 0; i < urls.length; i += 5) {
      await Promise.all(
        urls.slice(i, i + 5).map((url) =>
          fetch(url)
            .then((r) => { if (r.ok) cache.put(url, r) })
            .catch(() => {})
        )
      )
    }
  } catch {}
}

// Pré-cache les réponses Inertia de toutes les routes métier (appelé depuis app.ts)
async function precacheInertiaRoutes(routes, version) {
  const cache = await caches.open(INERTIA_CACHE)
  for (const route of routes) {
    try {
      const headers = {
        'X-Inertia': 'true',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      }
      if (version) headers['X-Inertia-Version'] = version
      const req = new Request(route, { headers, credentials: 'include' })
      const resp = await fetch(req)
      if (resp.ok) cache.put(req, resp.clone())
    } catch {}
  }
}

// NetworkFirst pour les requêtes Inertia (X-Inertia: true).
// Fallback 1 : réponse Inertia mise en cache lors d'une visite précédente.
// Fallback 2 : réponse Inertia synthétique avec props vides → la page charge depuis Dexie.
async function inertiaNetworkFirst(request) {
  const cache = await caches.open(INERTIA_CACHE)
  try {
    const response = await fetch(request)
    if (response.ok) {
      cache.put(request, response.clone())
    }
    return response
  } catch {
    const cached = await cache.match(request)
    if (cached) return cached

    // Réponse Inertia synthétique — Inertia rend le bon composant,
    // qui charge ses données depuis Dexie via onMounted (offlineStore.isOnline === false).
    const url = new URL(request.url)
    const page = getOfflinePage(url.pathname)
    if (page) {
      return new Response(
        JSON.stringify({
          component: page.component,
          props: page.props,
          url: url.pathname + url.search,
          version: 'offline',
        }),
        {
          status: 200,
          headers: {
            'Content-Type': 'application/json',
            'X-Inertia': 'true',
            'Vary': 'X-Inertia',
          },
        }
      )
    }

    return new Response('Hors ligne', { status: 503 })
  }
}

// NetworkFirst pour les appels API GET — timeout 3s puis fallback JSON offline
async function apiNetworkFirst(request) {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 3000)

  try {
    const response = await fetch(request, { signal: controller.signal })
    clearTimeout(timeout)
    return response
  } catch {
    clearTimeout(timeout)
    return new Response(
      JSON.stringify({ offline: true, data: [], message: 'Hors ligne — données locales utilisées' }),
      {
        status: 503,
        headers: { 'Content-Type': 'application/json' },
      }
    )
  }
}

async function cacheFirst(request) {
  const cached = await caches.match(request)
  if (cached) return cached
  try {
    const response = await fetch(request)
    if (response.ok) {
      const cache = await caches.open(ASSET_CACHE)
      cache.put(request, response.clone())
    }
    return response
  } catch {
    return new Response('Asset indisponible', { status: 503 })
  }
}

// Navigation HTML (hard refresh ou premier chargement).
// Fallback 1 : réponse réseau fraîche.
// Fallback 2 : shell HTML patché avec les données de page correctes → Inertia monte le bon composant.
async function navigateFallback(request) {
  const cache = await caches.open(SHELL_CACHE)

  const tryNetwork = async () => {
    const response = await fetch(request.clone())
    if (response.ok) cache.put(request, response.clone())
    return response
  }

  try {
    return await tryNetwork()
  } catch {
    // Possible faux-positif au démarrage Tauri — on retente après 2s
    await new Promise(r => setTimeout(r, 2000))
    try {
      return await tryNetwork()
    } catch {
      // Vraie coupure réseau
      const routeCached = await cache.match(request)
      if (routeCached) return routeCached

      // Patcher le shell HTML avec les données du bon composant Inertia
      const url = new URL(request.url)
      const page = getOfflinePage(url.pathname)
      const shell = await cache.match('/')

      if (page && shell) {
        const html = await shell.text()
        const pageJson = JSON.stringify({
          component: page.component,
          props: page.props,
          url: url.pathname,
          version: 'offline',
        }).replace(/"/g, '&quot;')

        // Remplace la valeur de data-page dans le premier div#app trouvé
        const patched = html.replace(/data-page="[^"]*"/, `data-page="${pageJson}"`)
        if (patched !== html) {
          return new Response(patched, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
          })
        }
      }

      return (await cache.match('/offline.html')) || new Response('Hors ligne', { status: 503 })
    }
  }
}
