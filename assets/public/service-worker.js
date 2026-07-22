const pwaConfig = self.WP_PWA_BUILDER || {};
const cacheName = pwaConfig.cacheName || 'wp-pwa-builder';

function shouldHandleRequest(request) {
  if (request.method !== 'GET') {
    return false;
  }

  const url = new URL(request.url);

  if (!['http:', 'https:'].includes(url.protocol)) {
    return false;
  }

  if (pwaConfig.scope && !url.href.startsWith(pwaConfig.scope)) {
    return false;
  }

  return true;
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(cacheName).then((cache) => {
      if (!pwaConfig.offlineUrl) {
        return undefined;
      }

      return cache.add(new Request(pwaConfig.offlineUrl, { credentials: 'same-origin' }));
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('wp-pwa-builder-') && key !== cacheName)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (!shouldHandleRequest(event.request)) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }

        const responseToCache = response.clone();

        event.waitUntil(
          caches.open(cacheName).then((cache) => cache.put(event.request, responseToCache))
        );

        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match(pwaConfig.offlineUrl)))
  );
});
