<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Stats {
    total_users: number;
    total_organizers: number;
    pending_organizers: number;
    total_museums: number;
    published_museums: number;
    today_bookings: number;
    confirmed_bookings: number;
    today_revenue: number;
    total_revenue: number;
    total_tickets: number;
    pending_anomalies: number;
}

interface RecentBooking {
    id: number;
    code: string;
    museum_name: string;
    user_name: string;
    status: string;
    total_amount: number;
    created_at: string;
}

interface Log {
    id: number;
    action: string;
    actor_name?: string | null;
    actor_email?: string | null;
    ip_address?: string | null;
    created_at: string;
}

const props = defineProps<{
    stats: Stats;
    recentBookings: RecentBooking[];
    recentLogs: Log[];
}>();

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <AdminLayout>
        <Head title="Dashboard Admin" />

        <div>
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-slate-500 uppercase">Pengguna</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.total_users }}</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-emerald-600 uppercase">Pengelola Aktif</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.total_organizers }}</div>
                    <div class="text-xs text-amber-600">{{ stats.pending_organizers }} menunggu</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-blue-600 uppercase">Museum Published</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.published_museums }}<span class="text-sm text-slate-400">/{{ stats.total_museums }}</span></div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-rose-600 uppercase">Anomali</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.pending_anomalies }}</div>
                    <div class="text-xs text-slate-500">Perlu review</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-purple-600 uppercase">Booking Hari Ini</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.today_bookings }}</div>
                    <div class="text-xs text-slate-500">{{ stats.confirmed_bookings }} total confirmed</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-emerald-600 uppercase">Pendapatan Hari Ini</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ formatRupiah(stats.today_revenue) }}</div>
                    <div class="text-xs text-slate-500">Total: {{ formatRupiah(stats.total_revenue) }}</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-slate-500 uppercase">Tiket Terbit</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.total_tickets }}</div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-sm">Booking Terkini</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Kode</th>
                                <th class="px-5 py-3">Museum</th>
                                <th class="px-5 py-3">Pemesan</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="b in recentBookings" :key="b.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3 font-mono font-semibold text-slate-900">{{ b.code }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ b.museum_name }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ b.user_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ b.status }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-900">
                                    {{ formatRupiah(b.total_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Logs -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-900 text-sm">Log Aktivitas Terkini</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Waktu</th>
                                <th class="px-5 py-3">Aktor</th>
                                <th class="px-5 py-3">Aksi</th>
                                <th class="px-5 py-3">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in recentLogs" :key="log.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ log.created_at }}</td>
                                <td class="px-5 py-3 text-xs">
                                    <span v-if="log.actor_name" class="font-medium text-slate-900">{{ log.actor_name }}</span>
                                    <span v-else class="italic text-slate-400">Sistem</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">{{ log.action }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ log.ip_address || '-' }}</td>
                            </tr>
                            <tr v-if="recentLogs.length === 0">
                                <td colspan="4" class="px-5 py-8 text-center text-slate-500">Belum ada aktivitas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
