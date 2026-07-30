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

function isStaticAssetRequest(request) {
  return ['style', 'script', 'image', 'font'].includes(request.destination);
}

function shouldCacheRequest(request, response) {
  if (!shouldHandleRequest(request)) {
    return false;
  }

  if (!isStaticAssetRequest(request)) {
    return false;
  }

  if (!response || response.status !== 200 || response.type !== 'basic') {
    return false;
  }

  const url = new URL(request.url);

  if (url.search) {
    return false;
  }

  if (
    url.pathname.endsWith('/start/') ||
    url.pathname.endsWith('/manifest.webmanifest') ||
    url.pathname.endsWith('/sw.js') ||
    url.pathname.includes('/wp-admin/') ||
    url.pathname.includes('/wp-json/')
  ) {
    return false;
  }

  return true;
}

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key.startsWith('wp-pwa-builder-') && key !== cacheName)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (!shouldHandleRequest(event.request) || !isStaticAssetRequest(event.request)) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (!shouldCacheRequest(event.request, response)) {
          return response;
        }

        const responseToCache = response.clone();

        event.waitUntil(
          caches.open(cacheName).then((cache) => cache.put(event.request, responseToCache))
        );

        return response;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || Response.error()))
  );
});
