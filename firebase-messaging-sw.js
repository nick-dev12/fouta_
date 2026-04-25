/* Service Worker pour Firebase Cloud Messaging - Notifications push
   Config à synchroniser avec config/firebase_config.php */

self.addEventListener('install', function() {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

importScripts('https://www.gstatic.com/firebasejs/12.9.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.9.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyAOGTcYf7i-Jj6jj5KuTOJboFVagkbdBW4",
    authDomain: "sugar-paper.firebaseapp.com",
    projectId: "sugar-paper",
    storageBucket: "sugar-paper.firebasestorage.app",
    messagingSenderId: "409713248489",
    appId: "1:409713248489:web:6bff9f5584e52c05a04878"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function(payload) {
    const notificationTitle = payload.notification?.title || payload.data?.title || 'FOUTA POIDS LOURDS';
    const notificationOptions = {
        body: payload.notification?.body || payload.data?.body || '',
        icon: '/image/produit1.jpg',
        badge: '/image/produit1.jpg',
        tag: payload.data?.tag || 'sugar-paper',
        data: payload.data || {},
        requireInteraction: false,
        actions: payload.data?.link ? [
            { action: 'open', title: 'Voir' }
        ] : []
    };

    return self.registration.showNotification(notificationTitle, notificationOptions);
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data?.link || event.notification.data?.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
