/**
 * Service Worker PWA — installation et mode application
 * Fetch : réseau direct (pas de stratégie cache pour éviter données périmées admin/vitrine).
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    event.respondWith(fetch(event.request));
});
