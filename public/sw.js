const SHELL_CACHE   = 'primegest-shell-v1'
const INERTIA_CACHE = 'primegest-inertia-v3'
const ASSET_CACHE   = 'primegest-assets-v1'

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

  if (request.method !== 'GET') return
  if (url.origin !== self.location.origin) return

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
    console.log('[SW] Inertia intercept:', url.pathname)
    event.respondWith(staleWhileRevalidate(request))
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(navigateFallback(request))
    return
  }
})

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
  console.log('[SW] cache hit:', !!cached, request.url)

  const fetchPromise = fetch(request)
    .then((response) => {
      const ct = response.headers.get('content-type') || ''
      console.log('[SW] response ct:', ct, 'ok:', response.ok)
      if (response.ok && ct.includes('application/json')) {
        cache.put(request, response.clone())
        console.log('[SW] cached:', request.url)
        self.clients.matchAll().then((clients) =>
          clients.forEach((client) =>
            client.postMessage({ type: 'INERTIA_CACHE_UPDATED', url: request.url })
          )
        )
      }
      return response
    })
    .catch((e) => { console.log('[SW] fetch error:', e); return null })

  if (cached) {
    fetchPromise
    return cached
  }

  const network = await fetchPromise
  if (network) return network

  return caches.match('/offline.html')
}

async function navigateFallback(request) {
  try {
    const response = await fetch(request)
    if (response.ok) return response
    throw new Error()
  } catch {
    const shell = await caches.match('/')
    return shell || caches.match('/offline.html')
  }
}
