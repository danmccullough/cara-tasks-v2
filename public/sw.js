// Service worker: shows push notifications and opens the app when one is tapped.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    const { title = 'Cara Tasks', ...options } = payload;

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const existing = windows.find((w) => new URL(w.url).origin === self.location.origin);
            if (existing) {
                existing.navigate(url);
                return existing.focus();
            }
            return self.clients.openWindow(url);
        }),
    );
});
