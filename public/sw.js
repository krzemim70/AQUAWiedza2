const CACHE_NAME = 'aikwarium-static-v1';
const OFFLINE_URL = '/offline';

function sanitizeTargetUrl(rawUrl) {
    if (typeof rawUrl !== 'string' || rawUrl.trim() === '') {
        return '/';
    }

    const trimmed = rawUrl.trim();
    if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
        return trimmed;
    }

    try {
        const parsed = new URL(trimmed, self.location.origin);
        if (parsed.origin === self.location.origin) {
            return parsed.pathname + parsed.search + parsed.hash;
        }
    } catch {
        // Niepoprawny adres wraca na stronę główną.
    }

    return '/';
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Buforujemy wyłącznie wersjonowane assety Vite z /build/. Nigdy stron prywatnych ani odpowiedzi API.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(event.request).then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                    }

                    return response;
                });
            }),
        );

        return;
    }

    // Dla nawigacji zawsze sieć; brak połączenia pokazuje wyłącznie statyczną stronę offline.
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(OFFLINE_URL)),
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch {
            data = { body: event.data.text() };
        }
    }

    const title = typeof data.title === 'string' && data.title.trim() !== '' ? data.title : 'Aikwarium';
    const options = {
        body: typeof data.body === 'string' ? data.body : '',
        icon: '/icons/aikwarium-icon.svg',
        badge: '/icons/aikwarium-icon.svg',
        data: { url: sanitizeTargetUrl(data.url) },
    };

    if (typeof data.tag === 'string' && data.tag !== '') {
        options.tag = data.tag;
    }

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetPath = sanitizeTargetUrl(event.notification.data && event.notification.data.url);
    const targetUrl = new URL(targetPath, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            if ('openWindow' in self.clients) {
                return self.clients.openWindow(targetUrl);
            }

            return undefined;
        }),
    );
});
