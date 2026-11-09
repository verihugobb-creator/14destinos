// 14 Destinos — Service Worker
// v3 = versión Vue. Los JS/CSS de Vite llevan un "hash" en el nombre
// (p. ej. /assets/index-a1b2c3.js), así que se cachean solos al pedirlos.
const CACHE = '14destinos-v6';
const STATIC = [
  './',
  './manifest.json',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE)
      .then(c => c.addAll(STATIC).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // API calls — always network, no cache
  if (url.pathname.includes('/api/')) {
    e.respondWith(
      fetch(e.request).catch(() =>
        new Response(JSON.stringify({success:false,message:'Sin conexión'}),
          { headers: {'Content-Type':'application/json'} })
      )
    );
    return;
  }

  // Map tiles — network first, cache fallback (fix: use cache.put with clone)
  if (url.hostname.includes('cartocdn') || url.hostname.includes('arcgisonline')) {
    e.respondWith(
      caches.open(CACHE + '-tiles').then(cache =>
        fetch(e.request).then(response => {
          if (response.ok) {
            cache.put(e.request, response.clone());
          }
          return response;
        }).catch(() => cache.match(e.request))
      )
    );
    return;
  }

  // Static assets — cache first, then network
  e.respondWith(
    caches.match(e.request).then(cached => {
      if (cached) return cached;
      return fetch(e.request).then(response => {
        if (response.ok && response.type === 'basic') {
          caches.open(CACHE).then(cache => cache.put(e.request, response.clone()));
        }
        return response;
      });
    })
  );
});
