<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '../types';

const page = usePage<SharedProps>();
const appName = computed(() => page.props.appName || 'Jejak');
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex flex-col md:flex-row">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-slate-900 text-white flex-shrink-0 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-slate-800 gap-3">
                <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-sm">A</span>
                <div>
                    <h1 class="font-bold text-sm leading-tight">{{ appName }}</h1>
                    <p class="text-[10px] text-indigo-400 font-medium">PANEL ADMIN</p>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1 text-sm font-medium">
                <Link href="/admin" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-slate-800 text-white">
                    <span>Dashboard</span>
                </Link>
                <Link href="/admin/organizers" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Verifikasi Pengelola</span>
                </Link>
                <Link href="/admin/museums" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Moderasi Museum</span>
                </Link>
                <Link href="/admin/users" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Kelola Pengguna</span>
                </Link>
                <Link href="/admin/transactions" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Transaksi & Anomali</span>
                </Link>
                <Link href="/admin/activity-logs" class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <span>Log Aktivitas</span>
                </Link>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="text-xs text-slate-400 mb-2 truncate">{{ user?.name }} (Admin)</div>
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
                    <slot name="header">Admin Platform</slot>
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
