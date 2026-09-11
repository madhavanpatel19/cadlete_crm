// Universal Service Worker for CRM Web Notifications
'use strict';

const SW_VERSION = 'crm-sw-v4-2026-09-11';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        Promise.all([
            clients.claim(),
            self.registration.update().catch(function () {})
        ])
    );
});

self.addEventListener('push', function (event) {
    if (!event.data) return;

    var data = {};
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'CRM Notification', message: event.data.text() };
    }

    var targetUrl = (data && data.url) ? data.url : self.registration.scope;
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

    var rawUrl = (event.notification && event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : self.registration.scope;

    var finalUrl = self.registration.scope;
    try {
        if (rawUrl && (rawUrl.startsWith('http://') || rawUrl.startsWith('https://'))) {
            finalUrl = rawUrl;
        } else if (rawUrl && rawUrl !== '#' && rawUrl !== 'javascript:void(0);') {
            finalUrl = new URL(rawUrl, self.registration.scope).href;
        }
    } catch (e) {
        finalUrl = self.registration.scope;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // Find existing CRM tab in current scope or origin
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url && (client.url.indexOf(self.registration.scope) === 0 || client.url.indexOf(self.location.origin) === 0)) {
                    return client.focus().then(function (focusedClient) {
                        var target = focusedClient || client;
                        if (target) {
                            // Notify the client page directly to navigate
                            try {
                                target.postMessage({
                                    action: 'crm_notification_navigate',
                                    url: finalUrl
                                });
                            } catch (err) {}

                            // If supported and url is different, navigate client
                            if ('navigate' in target && target.url !== finalUrl && finalUrl && finalUrl !== '#' && !finalUrl.endsWith('#')) {
                                return target.navigate(finalUrl).catch(function () {
                                    if (clients.openWindow) {
                                        return clients.openWindow(finalUrl);
                                    }
                                });
                            }
                        }
                    }).catch(function () {
                        if (clients.openWindow) {
                            return clients.openWindow(finalUrl);
                        }
                    });
                }
            }

            // If no window is already open, open a fresh window
            if (clients.openWindow) {
                return clients.openWindow(finalUrl);
            }
        })
    );
});
