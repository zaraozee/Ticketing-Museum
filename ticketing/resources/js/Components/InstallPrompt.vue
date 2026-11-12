<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { applyUpdate, isIOS, isStandalone, onUpdateAvailable, showInstallPrompt } from '../pwa';

/**
 * Ajakan instal PWA (FR-PWA-04) dan prompt pembaruan service worker (AC-4).
 *
 * - Android/desktop: `beforeinstallprompt` ditangkap `pwa.ts` lalu tombol
 *   "Pasang" memanggil prompt asli.
 * - iOS: tidak ada prompt; tampilkan petunjuk "Tambahkan ke Layar Utama".
 * - Pembaruan: muncul sebagai banner, tidak pernah memuat ulang otomatis.
 */
const showBanner = ref(false);
const showIOSGuide = ref(false);
const updateReady = ref(false);
const dismissed = ref(false);

let unsubscribeUpdate: (() => void) | null = null;

const DISMISS_KEY = 'pwa-install-dismissed-at';
const DISMISS_DAYS = 14;

function dismissedRecently(): boolean {
    const raw = localStorage.getItem(DISMISS_KEY);

    if (raw === null) {
        return false;
    }

    const dismissedAt = Number(raw);

    if (Number.isNaN(dismissedAt)) {
        return false;
    }

    return Date.now() - dismissedAt < DISMISS_DAYS * 24 * 60 * 60 * 1000;
}

function onInstallAvailable(): void {
    if (!isStandalone() && !dismissedRecently()) {
        showBanner.value = true;
    }
}

function install(): void {
    showInstallPrompt();
    showBanner.value = false;
}

function dismiss(): void {
    dismissed.value = true;
    showBanner.value = false;
    localStorage.setItem(DISMISS_KEY, String(Date.now()));
}

function closeIOSGuide(): void {
    showIOSGuide.value = false;
    localStorage.setItem(DISMISS_KEY, String(Date.now()));
}

function reloadForUpdate(): void {
    updateReady.value = false;
    applyUpdate();
}

onMounted(() => {
    unsubscribeUpdate = onUpdateAvailable(() => {
        updateReady.value = true;
    });

    if (isStandalone() || dismissedRecently()) {
        return;
    }

    if (isIOS()) {
        // iOS tidak mendukung `beforeinstallprompt`; tampilkan panduan manual
        // setelah pengguna sempat melihat halaman (jangan di awal).
        window.setTimeout(() => {
            showIOSGuide.value = true;
        }, 4000);

        return;
    }

    window.addEventListener('pwa-install-available', onInstallAvailable);
});

onUnmounted(() => {
    window.removeEventListener('pwa-install-available', onInstallAvailable);
    unsubscribeUpdate?.();
});
</script>

<template>
    <!-- Prompt pembaruan (AC-4): tidak pernah memutus checkout -->
    <div
        v-if="updateReady"
        class="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-xl"
        role="alertdialog"
        aria-live="polite"
        aria-labelledby="pwa-update-title"
    >
        <p id="pwa-update-title" class="text-sm font-semibold text-slate-900">Versi baru tersedia</p>
        <p class="mt-1 text-xs text-slate-600">
            Muat ulang untuk memakai versi terbaru. Pemesanan yang sedang berjalan tidak akan hilang.
        </p>
        <div class="mt-3 flex gap-2">
            <button
                type="button"
                class="flex-1 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                @click="reloadForUpdate"
            >
                Muat Ulang
            </button>
            <button
                type="button"
                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                @click="updateReady = false"
            >
                Nanti
            </button>
        </div>
    </div>

    <!-- Banner instal Android/desktop -->
    <div
        v-if="showBanner"
        class="fixed inset-x-3 bottom-3 z-40 mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-4 shadow-xl md:inset-x-auto md:right-4 md:mx-0"
        role="region"
        aria-label="Pasang aplikasi"
    >
        <div class="flex items-start gap-3">
            <img src="/icons/icon-192.png" alt="" class="h-10 w-10 flex-shrink-0 rounded-xl" width="40" height="40" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900">Pasang aplikasi</p>
                <p class="mt-0.5 text-xs text-slate-600">Akses lebih cepat dan buka e-ticket saat offline.</p>
            </div>
            <button
                type="button"
                class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                aria-label="Tutup ajakan instal"
                @click="dismiss"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <button
            type="button"
            class="mt-3 w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            @click="install"
        >
            Pasang Sekarang
        </button>
    </div>

    <!-- Panduan instal iOS -->
    <div
        v-if="showIOSGuide"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pwa-ios-title"
    >
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 sm:p-6">
            <div class="mb-4 text-center">
                <img src="/icons/icon-192.png" alt="" class="mx-auto mb-3 h-12 w-12 rounded-xl" width="48" height="48" />
                <h2 id="pwa-ios-title" class="text-lg font-bold text-slate-900">Pasang di iOS</h2>
            </div>
            <ol class="space-y-3 text-sm text-slate-700">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">1</span>
                    <span>Ketuk tombol <strong>Bagikan</strong> di Safari.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">2</span>
                    <span>Pilih <strong>Tambah ke Layar Utama</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold">3</span>
                    <span>Ketuk <strong>Tambah</strong>.</span>
                </li>
            </ol>
            <button
                type="button"
                class="mt-5 w-full rounded-xl bg-slate-900 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                @click="closeIOSGuide"
            >
                Mengerti
            </button>
        </div>
    </div>
</template>
