<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/build/assets/smartgest.png">
    @vite(['resources/js/app.js'])
    @inertiaHead
    <link rel="manifest" href="/manifest.webmanifest">
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js')
        .then(r => console.log('SW registered:', r.scope))
        .catch(e => console.log('SW error:', e))
    })
  }
</script>
  </head>
  <body>
    @inertia
  </body>
</html>
