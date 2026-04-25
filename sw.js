/**
 * Service Worker PWA - Installation comme application
 * Permet l'installation du site sur l'écran d'accueil (mobile/desktop)
 */
self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});
