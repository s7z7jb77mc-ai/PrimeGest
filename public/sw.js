// Service Worker désactivé temporairement
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', () => self.clients.claim())
self.addEventListener('fetch', (event) => {
  // Ne rien cacher — laisser passer toutes les requêtes
  return
})
