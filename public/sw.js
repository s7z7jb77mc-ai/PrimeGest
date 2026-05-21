const SHELL_CACHE   = 'primegest-shell-v3'
const INERTIA_CACHE = 'primegest-inertia-v4'
const ASSET_CACHE   = 'primegest-assets-v2'

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
    caches.open(SHELL_CACHE).then((cache) =>
      cache.addAll(['/', '/offline.html'])
    )
  )
  self.skipWaiting()
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
    // Network-first pour éviter les boucles de reload dues au mismatch de version Inertia
    event.respondWith(inertiaNetworkFirst(request))
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(navigateFallback(request))
    return
  }
})

// NetworkFirst pour les requêtes Inertia — évite les boucles de mismatch de version
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

async function staleWhileRevalidate(request) {
  const cache = await caches.open(INERTIA_CACHE)
  const cached = await cache.match(request)

  const fetchPromise = fetch(request)
    .then((response) => {
      const ct = response.headers.get('content-type') || ''
      if (response.ok && ct.includes('application/json')) {
        cache.put(request, response.clone())
        self.clients.matchAll().then((clients) =>
          clients.forEach((client) =>
            client.postMessage({ type: 'INERTIA_CACHE_UPDATED', url: request.url })
          )
        )
      }
      return response
    })
    .catch(() => null)

  if (cached) {
    fetchPromise
    return cached
  }

  const network = await fetchPromise
  if (network) return network

  return caches.match('/offline.html')
}

async function navigateFallback(request) {
  const cache = await caches.open(SHELL_CACHE)
  try {
    const response = await fetch(request)
    if (response.ok) {
      cache.put(request, response.clone())
      return response
    }
    throw new Error()
  } catch {
    const routeCached = await cache.match(request)
    if (routeCached) return routeCached
    const rootCached = await cache.match('/')
    if (rootCached) return rootCached
    return (await cache.match('/offline.html')) || new Response('Hors ligne', { status: 503 })
  }
}
