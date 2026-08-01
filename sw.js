/**
 * Service Worker PWA — installation et mode application.
 * Pas d'interception fetch : évite ERR_FAILED si SSL/réseau instable (prod Webuzo).
 */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});
