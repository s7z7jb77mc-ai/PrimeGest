const CACHE_NAME = 'primegest-v1';

const STATIC_ASSETS = [
    '/',
    '/dashboard',
    '/login',
];

// Installation — cache les assets statiques
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activation — nettoyer les anciens caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Fetch — stratégie Network First avec fallback cache
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Assets Vite — Cache First (ils ont un hash, jamais obsolètes)
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached;
                return fetch(event.request).then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clone);
                    });
                    return response;
                });
            })
        );
        return;
    }

    // API sync — toujours réseau, jamais cache
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Pages Inertia — Network First avec fallback cache
    if (event.request.mode === 'navigate' || event.request.headers.get('X-Inertia')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clone);
                    });
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request).then((cached) => {
                        if (cached) return cached;
                        // Fallback vers le dashboard caché
                        return caches.match('/dashboard');
                    });
                })
        );
        return;
    }

    // Tout le reste — Network First
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
