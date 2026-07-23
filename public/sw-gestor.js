const CACHE_NAME = 'unitec-executivo-v6';
const OFFLINE_URL = '/pwa-gestor/offline.html';
const PRECACHE = [
  OFFLINE_URL,
  '/css/erp-gestor.css',
  '/pwa-gestor/icons/icon-192.png',
  '/pwa-gestor/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('push', (event) => {
  let data = {
    title: 'Unitec Executivo',
    body: 'Nova atualização',
    url: '/gestor/',
    tag: 'gestor',
    icon: '/pwa-gestor/icons/icon-192.png',
  };

  try {
    if (event.data) {
      data = Object.assign(data, event.data.json());
    }
  } catch (e) {
    try {
      data.body = event.data ? event.data.text() : data.body;
    } catch (e2) {}
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'Unitec Executivo', {
      body: data.body || '',
      icon: data.icon || '/pwa-gestor/icons/icon-192.png',
      badge: data.icon || '/pwa-gestor/icons/icon-192.png',
      tag: data.tag || 'gestor',
      data: { url: data.url || '/gestor/' },
      renotify: true,
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/gestor/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
      const abs = new URL(target, self.location.origin).href;
      for (const client of list) {
        const href = client.url || '';
        if (href.indexOf('/gestor') !== -1 && 'focus' in client) {
          if ('navigate' in client) {
            client.navigate(abs);
          }
          return client.focus();
        }
      }
      for (const client of list) {
        if ('focus' in client) {
          if ('navigate' in client) {
            client.navigate(abs);
          }
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(abs);
      }
    })
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  // Manifesto nunca em cache: display standalone precisa chegar fresco.
  if (url.pathname === '/manifest-gestor.webmanifest') {
    event.respondWith(
      fetch(req, { cache: 'no-store' }).catch(() => caches.match(req))
    );
    return;
  }

  const isGestorNav = url.pathname === '/gestor' || url.pathname.startsWith('/gestor/');
  const isGestorAsset =
    url.pathname === '/css/erp-gestor.css' ||
    url.pathname === '/sw-gestor.js' ||
    url.pathname.startsWith('/pwa-gestor/');

  if (!isGestorNav && !isGestorAsset) {
    return;
  }

  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(async () => {
          const cached = await caches.match(req);
          return cached || caches.match(OFFLINE_URL);
        })
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(req, copy)).catch(() => {});
        return res;
      });
    })
  );
});
