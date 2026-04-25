/**
 * Gestion des notifications push Firebase
 * Utilisé pour l'interaction (activation des notifications)
 */

(function() {
    'use strict';

    var VAPID_KEY = "BMyDk413EM5lcS6mucg6wl5SGB0JKEdCi188_5qjMPygFxHY-Na378jlVVyYD_6epN7AqOzZSa-FojzVnKBW-qg";

    function getApiUrl() {
        var base = window.location.pathname;
        if (base.indexOf('/admin/') === 0) return '../api/save_fcm_token.php';
        if (base.indexOf('/user/') === 0) return '../api/save_fcm_token.php';
        return '/api/save_fcm_token.php';
    }

    window.FirebaseNotifications = {
        init: function(type) {
            if (!('Notification' in window)) {
                alert('Votre navigateur ne supporte pas les notifications.');
                return Promise.resolve(false);
            }
            if (!('serviceWorker' in navigator)) {
                alert('Les notifications nécessitent un navigateur prenant en charge les Service Workers.');
                return Promise.resolve(false);
            }
            type = type || 'user';
            if (Notification.permission === 'granted') {
                return window.FirebaseNotifications.registerServiceWorker(type);
            }
            if (Notification.permission === 'denied') {
                alert('Les notifications ont été bloquées. Autorisez-les dans les paramètres du navigateur.');
                return Promise.resolve(false);
            }
            return this.requestPermission(type);
        },

        requestPermission: function(type) {
            type = type || 'user';
            return Notification.requestPermission().then(function(permission) {
                if (permission === 'denied') {
                    alert('Les notifications ont été bloquées. Autorisez-les dans les paramètres du navigateur.');
                    return false;
                }
                if (permission !== 'granted') {
                    return false;
                }
                return window.FirebaseNotifications.registerServiceWorker(type);
            }).catch(function(err) {
                console.error('Notification permission:', err);
                alert('Erreur lors de la demande de permission: ' + (err.message || err));
                return false;
            });
        },

        registerServiceWorker: function(type) {
            var swPath = '/firebase-messaging-sw.js';
            return navigator.serviceWorker.register(swPath)
                .then(function(reg) {
                    return reg.ready;
                })
                .then(function() {
                    return navigator.serviceWorker.ready;
                })
                .then(function(reg) {
                    return window.FirebaseNotifications.getToken(type, reg);
                })
                .catch(function(err) {
                    console.error('Service Worker:', err);
                    alert('Erreur Service Worker: ' + (err.message || err) + '. Vérifiez que firebase-messaging-sw.js est accessible à la racine du site.');
                    return false;
                });
        },

        getToken: function(type, serviceWorkerRegistration) {
            if (typeof firebase === 'undefined') {
                alert('Firebase n\'est pas chargé.');
                return Promise.resolve(false);
            }
            if (!firebase.messaging) {
                alert('Firebase Messaging n\'est pas chargé.');
                return Promise.resolve(false);
            }
            var messaging = firebase.messaging();
            var options = { vapidKey: VAPID_KEY };
            if (serviceWorkerRegistration) {
                options.serviceWorkerRegistration = serviceWorkerRegistration;
            }
            return messaging.getToken(options)
                .then(function(token) {
                    if (!token) return false;
                    return window.FirebaseNotifications.saveToken(token, type);
                })
                .catch(function(err) {
                    console.error('FCM getToken:', err);
                    var msg = err.message || String(err);
                    if (msg.indexOf('messaging/invalid-vapid-key') !== -1) {
                        alert('Clé VAPID invalide. Vérifiez la configuration Firebase.');
                    } else if (msg.indexOf('messaging/failed-service-worker') !== -1) {
                        alert('Le Service Worker a échoué. Réessayez ou vérifiez la console.');
                    } else {
                        alert('Erreur token FCM: ' + msg);
                    }
                    return false;
                });
        },

        saveToken: function(token, type) {
            var formData = new FormData();
            formData.append('token', token);
            formData.append('type', type);
            var url = getApiUrl();
            return fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function(r) {
                return r.json();
            }).then(function(d) {
                if (!d.success && d.message) {
                    console.warn('Save token:', d.message);
                }
                return d.success;
            }).catch(function(err) {
                console.error('Save token:', err);
                alert('Erreur lors de l\'enregistrement du token.');
                return false;
            });
        },

        enable: function(type, buttonEl) {
            var btn = buttonEl || document.getElementById('btn-enable-notifications');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Activation...';
            }
            return this.init(type).then(function(ok) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = ok ? '<i class="fas fa-bell"></i> Notifications activées' : '<i class="fas fa-bell-slash"></i> Activer les notifications';
                    if (ok) btn.classList.add('notifications-enabled');
                }
                if (ok) window.FirebaseNotifications.setupForegroundHandler();
                return ok;
            });
        },

        /**
         * Affiche les notifications quand la page est ouverte (premier plan).
         * Sans cela, les messages reçus avec l'onglet actif ne s'affichent pas.
         */
        _foregroundHandlerSetup: false,
        setupForegroundHandler: function() {
            if (window.FirebaseNotifications._foregroundHandlerSetup) return;
            if (typeof firebase === 'undefined' || !firebase.messaging) return;
            if (!('Notification' in window)) return;
            window.FirebaseNotifications._foregroundHandlerSetup = true;
            var messaging = firebase.messaging();
            messaging.onMessage(function(payload) {
                var title = payload.notification && payload.notification.title ? payload.notification.title : (payload.data && payload.data.title ? payload.data.title : 'FOUTA POIDS LOURDS');
                var body = payload.notification && payload.notification.body ? payload.notification.body : (payload.data && payload.data.body ? payload.data.body : '');
                var options = {
                    body: body,
                    icon: '/image/produit1.jpg',
                    tag: (payload.data && payload.data.tag) ? payload.data.tag : 'sugar-paper-' + Date.now(),
                    requireInteraction: false
                };
                if (Notification.permission === 'granted') {
                    try {
                        new Notification(title, options);
                    } catch (e) {
                        console.warn('Notification foreground:', e);
                    }
                }
            });
        }
    };

    if (typeof firebase !== 'undefined' && firebase.messaging && document.readyState !== 'loading') {
        window.FirebaseNotifications.setupForegroundHandler();
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof firebase !== 'undefined' && firebase.messaging) {
                setTimeout(function() { window.FirebaseNotifications.setupForegroundHandler(); }, 500);
            }
        });
    }
})();
