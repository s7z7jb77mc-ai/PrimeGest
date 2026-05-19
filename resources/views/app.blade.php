<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- PWA -->
    <meta name="theme-color" content="#111827">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PrimeGest">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" href="/icons/icon-192x192.png">

    <title inertia>{{ config('app.name', 'PrimeGest') }}</title>

    @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
  </head>
  <body class="font-sans antialiased">
    @inertia

    <script>
      // N'enregistre le SW que si on n'est pas dans Tauri (Tauri gère son propre contexte)
      if (!('__TAURI_INTERNALS__' in window) && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(r => console.log('[SW] scope:', r.scope))
            .catch(e => console.warn('[SW] erreur:', e))
        })
      }
    </script>
  </body>
</html>
