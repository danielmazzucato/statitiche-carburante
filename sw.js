const CACHE_NAME = 'mpm-carburante-cache-v2';
const STATIC_ASSETS = [
  'style.css',
  'app.js',
  'favicon.png',
  'favicon.ico',
  'logo.jpg'
];

// Install Event
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('[Service Worker] Caching static assets');
      return cache.addAll(STATIC_ASSETS).catch(err => {
        console.warn('[Service Worker] Pre-cache warning (some files might be missing):', err);
      });
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            console.log('[Service Worker] Removing old cache', key);
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event - Network first for PHP/dynamic content & user uploads, Cache first for static assets
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // For PHP files, HTML navigation, or user uploads, always try network first
  if (event.request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname.includes('/uploads/')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.match(event.request);
        })
    );
  } else {
    // For static assets, try cache first, fall back to network
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          return fetch(event.request).then(networkResponse => {
            // Cache newly fetched assets on the fly
            if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
              const responseToCache = networkResponse.clone();
              caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, responseToCache);
              });
            }
            return networkResponse;
          });
        })
    );
  }
});
