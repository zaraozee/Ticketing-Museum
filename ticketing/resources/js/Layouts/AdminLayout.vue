<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { SharedProps } from '../types';
import { clearOfflineTickets } from '../composables/offlineTickets';

const page = usePage<SharedProps>();
const appName = computed(() => page.props.appName || 'Jejak');
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});

/**
 * Navigasi mengikuti route yang ada di `routes/web.php`. Tautan mati
 * (`/admin/organizers`, `/admin/users`, `/admin/activity-logs`) dihapus pada
 * Fase 8 — verifikasi pengelola dilakukan lewat moderasi museum dan antrean
 * anomali, sesuai cakupan MVP.
 */
const links = [
    { href: '/admin', label: 'Dashboard' },
    { href: '/admin/museums', label: 'Moderasi Museum' },
    { href: '/admin/categories', label: 'Kategori Museum' },
    { href: '/admin/facilities', label: 'Master Fasilitas' },
    { href: '/admin/content-pages', label: 'Konten & FAQ' },
    { href: '/admin/reports', label: 'Laporan Platform' },
    { href: '/admin/anomalies', label: 'Transaksi & Anomali' },
];

const mobileMenuOpen = ref(false);

watch(
    () => page.url,
    () => {
        mobileMenuOpen.value = false;
    },
);

const isActive = (href: string): boolean => {
    const current = page.url.split('?')[0];

    return href === '/admin' ? current === '/admin' : current.startsWith(href);
};

const logout = (): void => {
    // Logout menghapus tiket offline di perangkat (SYSTEM_DESIGN §15.3).
    void clearOfflineTickets().finally(() => {
        router.post('/logout');
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-100 md:flex-row">
        <!-- Overlay drawer (mobile) -->
        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-30 bg-slate-900/60 md:hidden"
            aria-hidden="true"
            @click="mobileMenuOpen = false"
        />

        <!-- Sidebar / drawer -->
        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-40 flex w-64 max-w-[85vw] -translate-x-full flex-col bg-slate-900 text-white transition-transform duration-200 ease-out md:static md:w-64 md:max-w-none md:translate-x-0"
            :class="{ 'translate-x-0': mobileMenuOpen }"
        >
            <div class="flex h-16 flex-shrink-0 items-center gap-3 border-b border-slate-800 px-4 sm:px-6">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold">A</span>
                <div class="min-w-0">
                    <h1 class="truncate text-sm font-bold leading-tight">{{ appName }}</h1>
                    <p class="text-[10px] font-medium text-indigo-400">PANEL ADMIN</p>
                </div>
                <button
                    type="button"
                    class="ml-auto flex h-11 w-11 items-center justify-center rounded-lg text-slate-300 hover:bg-slate-800 md:hidden"
                    aria-label="Tutup menu"
                    @click="mobileMenuOpen = false"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6 text-sm font-medium" aria-label="Navigasi admin">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="flex min-h-[44px] items-center rounded-lg px-3 py-2 transition"
                    :class="
                        isActive(link.href)
                            ? 'bg-slate-800 text-white'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                    "
                    :aria-current="isActive(link.href) ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <div class="border-t border-slate-800 p-4">
                <div class="mb-2 truncate text-xs text-slate-400">{{ user?.name }} (Admin)</div>
                <div class="flex gap-2">
                    <Link
                        href="/"
                        class="flex-1 rounded bg-slate-800 px-3 py-2.5 text-center text-xs text-slate-200 hover:bg-slate-700"
                    >
                        Web Publik
                    </Link>
                    <button
                        type="button"
                        class="rounded bg-rose-900/60 px-3 py-2.5 text-xs text-rose-200 hover:bg-rose-900"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top bar -->
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
                <button
                    type="button"
                    class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 md:hidden"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="admin-sidebar"
                    aria-label="Buka menu navigasi"
                    @click="mobileMenuOpen = true"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h2 class="min-w-0 flex-1 truncate text-base font-semibold text-slate-800 sm:text-lg">
                    <slot name="header">Admin Platform</slot>
                </h2>
                <div class="hidden items-center gap-3 text-sm text-slate-600 sm:flex">
                    <span class="max-w-[16rem] truncate">{{ user?.email }}</span>
                </div>
            </header>

            <!-- Flash alerts -->
            <div
                v-if="flash.success"
                class="mx-4 mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 sm:mx-6"
                role="status"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash.error"
                class="mx-4 mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 sm:mx-6"
                role="alert"
            >
                {{ flash.error }}
            </div>

            <!-- Content Area -->
            <main class="flex-1 p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
