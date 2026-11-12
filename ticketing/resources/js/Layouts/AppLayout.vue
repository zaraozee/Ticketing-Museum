<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { SharedProps } from '../types';
import InstallPrompt from '../Components/InstallPrompt.vue';
import { useOnlineStatus } from '../composables/useOnlineStatus';
import { clearOfflineTickets } from '../composables/offlineTickets';

const page = usePage<SharedProps>();
const appName = computed(() => page.props.appName || 'Jejak');
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash ?? {});
const { online } = useOnlineStatus();

const mobileMenuOpen = ref(false);

const logout = (): void => {
    // Logout menghapus data tiket offline di perangkat (SYSTEM_DESIGN §15.3).
    void clearOfflineTickets().finally(() => {
        router.post('/logout');
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50">
        <!-- Indikator offline (FR-PWA-03) -->
        <div
            v-if="!online"
            class="bg-amber-500 px-4 py-2 text-center text-xs font-semibold text-amber-950"
            role="status"
        >
            Anda sedang offline. E-ticket tersimpan tetap dapat dibuka dari
            <Link href="/offline/tickets" class="underline">Tiket Tersimpan</Link>.
        </div>

        <nav class="sticky top-0 z-40 border-b border-slate-200 bg-white" aria-label="Navigasi utama">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex items-center gap-8">
                        <Link href="/" class="flex items-center gap-2" aria-label="Beranda">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white shadow-sm">
                                J
                            </span>
                            <span class="text-xl font-bold tracking-tight text-slate-900">{{ appName }}</span>
                        </Link>
                        <div class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                            <Link href="/museums" class="transition hover:text-blue-600">Jelajah Museum</Link>
                            <Link v-if="user" href="/tickets" class="transition hover:text-blue-600">Tiket Saya</Link>
                            <Link v-if="user" href="/notifications" class="transition hover:text-blue-600">Notifikasi</Link>
                            <Link v-if="user?.is_organizer" href="/organizer" class="font-semibold text-blue-600 hover:underline">
                                Area Pengelola
                            </Link>
                            <Link v-if="user?.is_admin" href="/admin" class="font-semibold text-indigo-600 hover:underline">
                                Area Admin
                            </Link>
                        </div>
                    </div>

                    <div class="hidden items-center gap-4 md:flex">
                        <template v-if="user">
                            <div class="flex items-center gap-3">
                                <Link href="/profile" class="text-sm font-medium text-slate-700 hover:text-blue-600">
                                    {{ user.name }}
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-700 transition hover:bg-slate-200"
                                    @click="logout"
                                >
                                    Keluar
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <Link href="/login" class="px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                                Masuk
                            </Link>
                            <Link
                                href="/register"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                            >
                                Daftar
                            </Link>
                        </template>
                    </div>

                    <div class="flex items-center md:hidden">
                        <button
                            type="button"
                            class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                            :aria-expanded="mobileMenuOpen"
                            aria-controls="mobile-menu"
                            aria-label="Buka menu"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path
                                    v-if="!mobileMenuOpen"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="mobileMenuOpen"
                id="mobile-menu"
                class="space-y-1 border-b border-slate-200 bg-white px-4 pb-4 pt-2 md:hidden"
            >
                <Link href="/museums" class="block rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">
                    Jelajah Museum
                </Link>
                <template v-if="user">
                    <Link href="/tickets" class="block rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">
                        Tiket Saya
                    </Link>
                    <Link href="/notifications" class="block rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">
                        Notifikasi
                    </Link>
                    <Link href="/profile" class="block rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">
                        Profil Akun
                    </Link>
                    <Link
                        v-if="user.is_organizer"
                        href="/organizer"
                        class="block rounded-lg px-3 py-3 text-base font-medium text-blue-600 hover:bg-blue-50"
                    >
                        Area Pengelola
                    </Link>
                    <Link
                        v-if="user.is_admin"
                        href="/admin"
                        class="block rounded-lg px-3 py-3 text-base font-medium text-indigo-600 hover:bg-indigo-50"
                    >
                        Area Admin
                    </Link>
                    <button
                        type="button"
                        class="block w-full rounded-lg px-3 py-3 text-left text-base font-medium text-rose-600 hover:bg-rose-50"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </template>
                <template v-else>
                    <Link href="/login" class="block rounded-lg px-3 py-3 text-base font-medium text-slate-700 hover:bg-slate-50">
                        Masuk
                    </Link>
                    <Link href="/register" class="block rounded-lg px-3 py-3 text-base font-medium text-blue-600 hover:bg-blue-50">
                        Daftar Akun
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Flash Message Banner -->
        <div v-if="flash?.success" class="bg-emerald-600 px-4 py-2 text-center text-sm font-medium text-white" role="status">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="bg-rose-600 px-4 py-2 text-center text-sm font-medium text-white" role="alert">
            {{ flash.error }}
        </div>
        <div v-if="flash?.status" class="bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white" role="status">
            {{ flash.status }}
        </div>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="mt-auto border-t border-slate-200 bg-white py-8">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 text-sm text-slate-500 sm:px-6 md:flex-row lg:px-8">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-600 text-xs font-bold text-white">J</span>
                    <span class="font-semibold text-slate-800">{{ appName }}</span>
                    <span>&copy; {{ new Date().getFullYear() }}</span>
                </div>
                <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-xs">
                    <Link href="/register/organizer" class="text-blue-600 hover:underline">Daftar Sebagai Pengelola Museum</Link>
                    <Link href="/bantuan/syarat-dan-ketentuan" class="hover:text-slate-800">Syarat &amp; Ketentuan</Link>
                    <Link href="/bantuan/kebijakan-privasi" class="hover:text-slate-800">Kebijakan Privasi</Link>
                    <Link href="/bantuan" class="hover:text-slate-800">Bantuan</Link>
                </div>
            </div>
        </footer>

        <InstallPrompt />
    </div>
</template>
