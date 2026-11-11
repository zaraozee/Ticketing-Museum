<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { SharedProps } from '../types';

const page = usePage<SharedProps>();
const appName = computed(() => page.props.appName || 'Jejak');
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const mobileMenuOpen = ref(false);

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col">
        <!-- Navigation Bar -->
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center gap-8">
                        <Link href="/" class="flex items-center gap-2">
                            <span class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                                J
                            </span>
                            <span class="text-xl font-bold tracking-tight text-slate-900">{{ appName }}</span>
                        </Link>
                        <div class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
                            <Link href="/museums" class="hover:text-blue-600 transition">Jelajah Museum</Link>
                            <Link v-if="user" href="/my-tickets" class="hover:text-blue-600 transition">Tiket Saya</Link>
                            <Link v-if="user?.is_organizer" href="/organizer" class="text-blue-600 font-semibold hover:underline">Area Pengelola</Link>
                            <Link v-if="user?.is_admin" href="/admin" class="text-indigo-600 font-semibold hover:underline">Area Admin</Link>
                        </div>
                    </div>

                    <!-- Right side buttons -->
                    <div class="hidden md:flex items-center gap-4">
                        <template v-if="user">
                            <div class="flex items-center gap-3">
                                <Link href="/profile" class="text-sm font-medium text-slate-700 hover:text-blue-600">
                                    {{ user.name }}
                                </Link>
                                <button
                                    @click="logout"
                                    class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg transition"
                                >
                                    Keluar
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <Link href="/login" class="text-sm font-medium text-slate-600 hover:text-slate-900 px-3 py-2">
                                Masuk
                            </Link>
                            <Link href="/register" class="text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-xl shadow-sm transition">
                                Daftar
                            </Link>
                        </template>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="flex items-center md:hidden">
                        <button
                            @click="mobileMenuOpen = !mobileMenuOpen"
                            class="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu dropdown -->
            <div v-if="mobileMenuOpen" class="md:hidden border-b border-slate-200 bg-white px-4 pt-2 pb-4 space-y-2">
                <Link href="/museums" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Jelajah Museum</Link>
                <template v-if="user">
                    <Link href="/my-tickets" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Tiket Saya</Link>
                    <Link href="/profile" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Profil Akun</Link>
                    <Link v-if="user.is_organizer" href="/organizer" class="block px-3 py-2 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50">Area Pengelola</Link>
                    <Link v-if="user.is_admin" href="/admin" class="block px-3 py-2 rounded-md text-base font-medium text-indigo-600 hover:bg-indigo-50">Area Admin</Link>
                    <button @click="logout" class="w-full text-left px-3 py-2 rounded-md text-base font-medium text-rose-600 hover:bg-rose-50">Keluar</button>
                </template>
                <template v-else>
                    <Link href="/login" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50">Masuk</Link>
                    <Link href="/register" class="block px-3 py-2 rounded-md text-base font-medium text-blue-600 hover:bg-blue-50">Daftar Akun</Link>
                </template>
            </div>
        </nav>

        <!-- Flash Message Banner -->
        <div v-if="flash.success" class="bg-emerald-600 text-white text-center py-2 px-4 text-sm font-medium">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="bg-rose-600 text-white text-center py-2 px-4 text-sm font-medium">
            {{ flash.error }}
        </div>
        <div v-if="flash.status" class="bg-blue-600 text-white text-center py-2 px-4 text-sm font-medium">
            {{ flash.status }}
        </div>

        <!-- Main Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 mt-auto py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-slate-500">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-xs">J</span>
                    <span class="font-semibold text-slate-800">{{ appName }}</span>
                    <span>&copy; {{ new Date().getFullYear() }}</span>
                </div>
                <div class="flex gap-6 text-xs">
                    <Link href="/register/organizer" class="text-blue-600 hover:underline">Daftar Sebagai Pengelola Museum</Link>
                    <Link href="/bantuan/syarat-dan-ketentuan" class="hover:text-slate-800">Syarat &amp; Ketentuan</Link>
                    <Link href="/bantuan/kebijakan-privasi" class="hover:text-slate-800">Kebijakan Privasi</Link>
                    <Link href="/bantuan" class="hover:text-slate-800">Bantuan</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
