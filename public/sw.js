const SHELL_CACHE   = 'primegest-shell-v9'
const INERTIA_CACHE = 'primegest-inertia-v10'
const ASSET_CACHE   = 'primegest-assets-v8'

const INERTIA_ROUTES = [
  '/dashboard',
  '/entreprises',
  '/produits',
  '/mouvement-stocks',
  '/caisse',
  '/journals',
  '/tiers',
  '/creances-dettes',
  '/rapports',
  '/transferts',
  '/succursales',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    Promise.all([
      caches.open(SHELL_CACHE).then((cache) =>
        // Résilient : une URL indisponible ne bloque pas l'installation du SW
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

    // Batches de 5 pour ne pas saturer le réseau
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

// NetworkFirst pour les requêtes Inertia — cache en cas de succès, fallback cache sinon
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
    return new Response(
      JSON.stringify({ error: 'Hors ligne', message: 'Données non disponibles hors connexion' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    )
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
    // Premier échec réseau — peut être un faux-positif au démarrage Tauri (race condition WebView/réseau).
    // On attend 2 s et on retente avant de basculer en mode hors-ligne.
    await new Promise(r => setTimeout(r, 2000))
    try {
      return await tryNetwork()
    } catch {
      // Vraie coupure réseau : servir le cache ou offline.html
      const routeCached = await cache.match(request)
      if (routeCached) return routeCached
      return (await cache.match('/offline.html')) || new Response('Hors ligne', { status: 503 })
    }
  }
}
