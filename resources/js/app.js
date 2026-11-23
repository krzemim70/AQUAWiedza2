//
document.documentElement.classList.add('js');
document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    const nav = document.getElementById(button.getAttribute('aria-controls'));
    const set = (open) => {
        button.setAttribute('aria-expanded', open);
        nav.toggleAttribute('data-open', open);
    };
    button.addEventListener('click', () => set(button.getAttribute('aria-expanded') !== 'true'));
    nav.addEventListener('keydown', (e) => e.key === 'Escape' && (set(false), button.focus()));
});

document.querySelectorAll('[data-copy-link]').forEach((button) => {
    const target = document.getElementById(button.getAttribute('data-copy-link'));
    const status = document.getElementById(button.getAttribute('data-copy-status'));
    if (!target || !navigator.clipboard) {
        return;
    }
    button.hidden = false;
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(target.value);
            if (status) {
                status.textContent = 'Skopiowano link do schowka.';
            }
        } catch {
            target.select();
            if (status) {
                status.textContent = 'Zaznaczono link — skopiuj go ręcznie.';
            }
        }
    });
});

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
}

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
};

document.querySelectorAll('[data-push-widget]').forEach((widget) => {
    const controls = widget.querySelector('[data-push-controls]');
    const toggle = widget.querySelector('[data-push-toggle]');
    const label = widget.querySelector('[data-push-label]');
    const status = widget.querySelector('[data-push-status]');
    if (!controls || !toggle || !label || !status) {
        return;
    }

    controls.hidden = false;

    const setChecked = (checked) => {
        toggle.setAttribute('aria-checked', checked ? 'true' : 'false');
        label.textContent = checked ? 'Powiadomienia push: włączone' : 'Powiadomienia push: wyłączone';
    };

    const setStatus = (message) => {
        status.textContent = message;
    };

    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    if (!supported) {
        toggle.disabled = true;
        setChecked(false);
        setStatus('Ta przeglądarka nie obsługuje powiadomień push. Na iPhonie i iPadzie najpierw dodaj Aikwarium do ekranu początkowego.');

        return;
    }

    const vapidKey = widget.dataset.vapidKey || '';
    const subscribeUrl = widget.dataset.subscribeUrl || '';
    const unsubscribeUrl = widget.dataset.unsubscribeUrl || '';
    const csrf = widget.dataset.csrf || '';
    const serverEnabled = widget.dataset.enabled === '1';

    navigator.serviceWorker.ready
        .then((reg) => reg.pushManager.getSubscription())
        .then((sub) => setChecked(serverEnabled && sub !== null))
        .catch(() => {});

    toggle.addEventListener('click', async () => {
        const currentlyOn = toggle.getAttribute('aria-checked') === 'true';
        toggle.disabled = true;
        toggle.setAttribute('aria-busy', 'true');

        try {
            if (!currentlyOn) {
                setStatus('Sprawdzanie uprawnień przeglądarki…');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    setChecked(false);
                    setStatus('Brak zgody przeglądarki na powiadomienia. Zmień uprawnienie w ustawieniach przeglądarki.');

                    return;
                }

                const reg = await navigator.serviceWorker.ready;
                let sub = await reg.pushManager.getSubscription();
                if (!sub) {
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidKey),
                    });
                }

                const json = sub.toJSON();
                const encodings = Array.isArray(PushManager.supportedContentEncodings) ? PushManager.supportedContentEncodings : [];
                const contentEncoding = encodings.includes('aes128gcm') ? 'aes128gcm' : (encodings[0] || 'aes128gcm');

                const res = await fetch(subscribeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({
                        endpoint: json.endpoint,
                        keys: json.keys || {},
                        content_encoding: contentEncoding,
                    }),
                });

                if (!res.ok) {
                    throw new Error('save_failed');
                }

                setChecked(true);
                setStatus('Powiadomienia push są włączone na tym urządzeniu.');
            } else {
                setStatus('Wyłączanie powiadomień push…');
                let endpoint = null;
                try {
                    const reg = await navigator.serviceWorker.ready;
                    const sub = await reg.pushManager.getSubscription();
                    if (sub) {
                        endpoint = sub.endpoint;
                        await sub.unsubscribe();
                    }
                } catch {
                    // Kontynuujemy usunięcie subskrypcji po stronie serwera.
                }

                const res = await fetch(unsubscribeUrl, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(endpoint ? { endpoint } : {}),
                });

                if (!res.ok) {
                    throw new Error('delete_failed');
                }

                setChecked(false);
                setStatus('Powiadomienia push zostały wyłączone.');
            }
        } catch {
            setStatus('Nie udało się zapisać ustawienia powiadomień push. Spróbuj ponownie.');
        } finally {
            toggle.disabled = false;
            toggle.removeAttribute('aria-busy');
        }
    });
});
