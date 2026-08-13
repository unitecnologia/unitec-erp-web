/* Unitec ERP — SW mínimo para instalabilidade PWA (sem cache de páginas). */
const SW_VERSION = 'unitec-erp-sw-v2';

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('unitec-erp') && key !== SW_VERSION)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

// Fetch handler obrigatório para o Chrome/Edge oferecer "Instalar app".
self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request).catch(() => Response.error())
  );
});
