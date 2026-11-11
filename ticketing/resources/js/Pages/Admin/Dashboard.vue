<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

interface Stats {
    total_users: number;
    pending_organizers: number;
    approved_organizers: number;
}

interface Log {
    id: number;
    action: string;
    actor?: {
        name: string;
        email: string;
    } | null;
    ip_address?: string | null;
    created_at: string;
}

defineProps<{
    stats: Stats;
    recentLogs: Log[];
}>();
</script>

<template>
    <AdminLayout>
        <Head title="Admin Dashboard" />

        <template #header>Ringkasan Platform</template>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Pengguna</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.total_users }}</div>
                <div class="mt-1 text-xs text-slate-500">Semua akun terdaftar</div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Menunggu Verifikasi</div>
                <div class="mt-2 text-3xl font-extrabold text-amber-600">{{ stats.pending_organizers }}</div>
                <div class="mt-1 text-xs text-slate-500">Pengelola museum baru</div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Pengelola Aktif</div>
                <div class="mt-2 text-3xl font-extrabold text-emerald-600">{{ stats.approved_organizers }}</div>
                <div class="mt-1 text-xs text-slate-500">Museum terverifikasi</div>
            </div>
        </div>

        <!-- Recent Activity Logs Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 text-sm">Log Aktivitas Terkini (Audit Trail)</h3>
                <span class="text-xs text-slate-500">10 aktivitas terakhir</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-3">Waktu</th>
                            <th class="px-6 py-3">Aktor</th>
                            <th class="px-6 py-3">Aksi</th>
                            <th class="px-6 py-3">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-slate-700">
                        <tr v-for="log in recentLogs" :key="log.id" class="hover:bg-slate-50/50">
                            <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500">
                                {{ new Date(log.created_at).toLocaleString('id-ID') }}
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-xs">
                                <span v-if="log.actor" class="font-medium text-slate-900">
                                    {{ log.actor.name }} ({{ log.actor.email }})
                                </span>
                                <span v-else class="text-slate-400 italic">Sistem / Publik</span>
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-md bg-slate-100 text-slate-800">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500">
                                {{ log.ip_address || '-' }}
                            </td>
                        </tr>
                        <tr v-if="recentLogs.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-slate-400 text-xs">
                                Belum ada aktivitas yang tercatat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
