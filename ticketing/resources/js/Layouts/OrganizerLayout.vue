<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '../types';

const page = usePage<SharedProps>();
const appName = computed(() => page.props.appName || 'Jejak');
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const orgProfile = computed(() => user.value?.organizer_profile);

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex flex-col md:flex-row">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-slate-900 text-white flex-shrink-0 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-slate-800 gap-3">
                <span class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center font-bold text-sm">P</span>
                <div>
                    <h1 class="font-bold text-sm leading-tight">{{ appName }}</h1>
                    <p class="text-[10px] text-blue-400 font-medium">AREA PENGELOLA</p>
                </div>
            </div>

            <!-- Organization Badge -->
            <div class="p-4 bg-slate-800/60 border-b border-slate-800">
                <div class="text-xs text-slate-400 font-medium">Organisasi / Badan</div>
                <div class="text-sm font-semibold text-white truncate mt-0.5">
                    {{ orgProfile?.organization_name || 'Pengelola Museum' }}
                </div>
                <div class="mt-1">
                    <span
                        v-if="orgProfile?.status === 'approved'"
                        class="inline-block px-2 py-0.5 text-[10px] rounded-full bg-emerald-500/20 text-emerald-300 font-semibold"
                    >
                        Terverifikasi
                    </span>
                    <span
                        v-else-if="orgProfile?.status === 'pending'"
                        class="inline-block px-2 py-0.5 text-[10px] rounded-full bg-amber-500/20 text-amber-300 font-semibold"
                    >
                        Menunggu Verifikasi Admin
                    </span>
                    <span
                        v-else
                        class="inline-block px-2 py-0.5 text-[10px] rounded-full bg-rose-500/20 text-rose-300 font-semibold"
                    >
                        Ditangguhkan
                    </span>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1 text-sm font-medium">
                <Link href="/organizer" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-slate-800 text-white">
                    <span>Dashboard</span>
                </Link>
                <Link href="/organizer/museums" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Museum Saya</span>
                </Link>
                <Link href="/organizer/schedules" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Tiket & Jadwal Kuota</span>
                </Link>
                <Link href="/organizer/bookings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Daftar Pemesanan</span>
                </Link>
                <Link href="/organizer/staff" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Staf Gate & Scanner</span>
                </Link>
                <Link href="/organizer/reports" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Laporan & Ekspor</span>
                </Link>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="text-xs text-slate-400 mb-2 truncate">{{ user?.name }}</div>
                <div class="flex gap-2">
                    <Link href="/" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded flex-1 text-center">Web Publik</Link>
                    <button @click="logout" class="text-xs bg-rose-900/60 hover:bg-rose-900 text-rose-200 px-3 py-1.5 rounded">Keluar</button>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-800">
                    <slot name="header">Dashboard Pengelola</slot>
                </h2>
                <div class="flex items-center gap-3 text-sm text-slate-600">
                    <span>{{ user?.email }}</span>
                </div>
            </header>

            <!-- Flash alerts -->
            <div v-if="flash.success" class="m-6 mb-0 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="m-6 mb-0 rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
                {{ flash.error }}
            </div>

            <!-- Content Area -->
            <main class="p-6 flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>
