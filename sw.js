// Universal Service Worker for CRM Web Notifications
'use strict';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', function (event) {
    if (!event.data) return;

    var data = {};
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'CRM Notification', message: event.data.text() };
    }

    var targetUrl = data.url || self.registration.scope;
    try {
        targetUrl = new URL(targetUrl, self.registration.scope).href;
    } catch (e) {
        targetUrl = self.registration.scope;
    }

    var options = {
        body: data.message || '',
        icon: 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png',
        badge: 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png',
        vibrate: [100, 50, 100],
        tag: 'crm-notif-' + (data.id || Date.now()),
        renotify: true,
        data: {
            url: targetUrl
        },
        requireInteraction: false
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'CRM Notification', options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var rawUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : self.registration.scope;

    var targetUrl = rawUrl;
    try {
        targetUrl = new URL(rawUrl, self.location.origin).href;
    } catch (e) {
        targetUrl = self.registration.scope;
    }

    var finalUrl = targetUrl;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // 1. Try to find an existing CRM tab on this origin, focus it, and navigate it
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url && client.url.indexOf(self.location.origin) !== -1 && 'focus' in client) {
                    return client.focus().then(function (focusedClient) {
                        var targetClient = focusedClient || client;
                        if (targetClient && 'navigate' in targetClient && finalUrl && finalUrl !== '#' && !finalUrl.endsWith('#')) {
                            return targetClient.navigate(finalUrl);
                        }
                    }).catch(function () {
                        if (clients.openWindow) {
                            return clients.openWindow(finalUrl);
                        }
                    });
                }
            }
            // 2. If no tab found, open a new window
            if (clients.openWindow) {
                return clients.openWindow(finalUrl);
            }
        })
    );
});
