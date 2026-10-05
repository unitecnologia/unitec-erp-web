/* Unitec Inventário — PWA online. Não armazena contagem nem catálogo. */
const INVENTARIO_SW_VERSION = '6';
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});
