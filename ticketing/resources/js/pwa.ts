/**
 * Pendaftaran service worker & prompt pembaruan (FR-PWA-02, AC-4).
 *
 * Service worker didaftarkan dari `/sw.js` (root) agar `scope` mencakup seluruh
 * aplikasi. Pembaruan **tidak** diterapkan otomatis: pengguna diminta
 * konfirmasi supaya alur checkout/pembayaran yang sedang berjalan tidak
 * terputus. `SKIP_WAITING` hanya dikirim setelah pengguna menyetujui.
 */

type UpdateCallback = () => void;

let waitingWorker: ServiceWorker | null = null;
let updateCallbacks: UpdateCallback[] = [];

export function onUpdateAvailable(callback: UpdateCallback): () => void {
    updateCallbacks.push(callback);

    if (waitingWorker) {
        callback();
    }

    return () => {
        updateCallbacks = updateCallbacks.filter((cb) => cb !== callback);
    };
}

/**
 * Terapkan versi baru: minta service worker menunggu untuk aktif lalu muat ulang
 * halaman saat controller sudah berganti.
 */
export function applyUpdate(): void {
    if (!waitingWorker) {
        window.location.reload();

        return;
    }

    let reloading = false;

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloading) {
            return;
        }

        reloading = true;
        window.location.reload();
    });

    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
}

function notifyUpdate(worker: ServiceWorker): void {
    waitingWorker = worker;
    updateCallbacks.forEach((callback) => callback());
}

export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    // Hanya pada konteks aman (HTTPS atau localhost) — syarat installability.
    if (!window.isSecureContext) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                // Service worker sudah menunggu saat halaman dimuat ulang.
                if (registration.waiting && navigator.serviceWorker.controller) {
                    notifyUpdate(registration.waiting);
                }

                registration.addEventListener('updatefound', () => {
                    const installing = registration.installing;

                    if (!installing) {
                        return;
                    }

                    installing.addEventListener('statechange', () => {
                        if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                            notifyUpdate(installing);
                        }
                    });
                });
            })
            .catch((error: unknown) => {
                // Jangan ganggu pengalaman pengguna bila registrasi gagal
                // (mis. build belum dibuat); cukup catat untuk diagnosis.
                console.warn('Registrasi service worker gagal:', error);
            });
    });
}

// ---------------------------------------------------------------------------
// Prompt instal (FR-PWA-04)
// ---------------------------------------------------------------------------

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let deferredPrompt: BeforeInstallPromptEvent | null = null;

export function setupInstallPrompt(): void {
    window.addEventListener('beforeinstallprompt', (event: Event) => {
        event.preventDefault();
        deferredPrompt = event as BeforeInstallPromptEvent;
        window.dispatchEvent(new CustomEvent('pwa-install-available'));
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        window.dispatchEvent(new CustomEvent('pwa-installed'));
    });
}

export function showInstallPrompt(): void {
    if (!deferredPrompt) {
        return;
    }

    void deferredPrompt.prompt();

    void deferredPrompt.userChoice.then((choice) => {
        if (choice.outcome === 'accepted') {
            deferredPrompt = null;
        }
    });
}

export function isInstallable(): boolean {
    return deferredPrompt !== null;
}

export function isIOS(): boolean {
    const ua = navigator.userAgent;

    return /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);
}

export function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (window.navigator as unknown as { standalone?: boolean }).standalone === true
    );
}
