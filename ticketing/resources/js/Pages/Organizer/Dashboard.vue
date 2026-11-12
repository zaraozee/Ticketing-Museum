<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrganizerLayout from '@/Layouts/OrganizerLayout.vue';

interface Profile {
    id: number;
    organization_name: string;
    status: string;
}

interface Stats {
    total_museums: number;
    published_museums: number;
    today_visitors: number;
    today_bookings: number;
    pending_bookings: number;
    confirmed_bookings: number;
    today_revenue: number;
    total_revenue: number;
    today_scans: number;
}

interface RecentBooking {
    id: number;
    code: string;
    museum_name: string;
    user_name: string;
    status: string;
    total_amount: number;
    visit_date: string;
    created_at: string;
}

const props = defineProps<{
    profile?: Profile | null;
    stats?: Stats | null;
    recentBookings?: RecentBooking[];
}>();

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

const statusBadge: Record<string, string> = {
    pending_payment: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-emerald-100 text-emerald-800',
    expired: 'bg-slate-100 text-slate-600',
    cancelled: 'bg-rose-100 text-rose-800',
};
</script>

<template>
    <OrganizerLayout>
        <Head title="Dashboard Pengelola" />

        <div>
            <!-- Status -->
            <div v-if="profile?.status === 'pending'" class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 p-6">
                <h3 class="font-bold text-amber-900">Akun Menunggu Verifikasi</h3>
                <p class="text-sm text-amber-800 mt-1">Pendaftaran "{{ profile?.organization_name }}" sedang ditinjau Admin.</p>
            </div>
            <div v-else-if="profile?.status === 'approved'" class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
                <p class="text-sm text-emerald-800">✓ Akun terverifikasi. Anda memiliki akses penuh.</p>
            </div>

            <!-- Stats -->
            <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-slate-500 uppercase">Museum</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.published_museums }}<span class="text-sm text-slate-400">/{{ stats.total_museums }}</span></div>
                    <div class="text-xs text-slate-500">Published/Total</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-blue-600 uppercase">Booking Hari Ini</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.today_bookings }}</div>
                    <div class="text-xs text-slate-500">{{ stats.pending_bookings }} pending</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-emerald-600 uppercase">Pendapatan Hari Ini</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ formatRupiah(stats.today_revenue) }}</div>
                    <div class="text-xs text-slate-500">Total: {{ formatRupiah(stats.total_revenue) }}</div>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold text-purple-600 uppercase">Scan Hari Ini</div>
                    <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ stats.today_scans }}</div>
                    <div class="text-xs text-slate-500">Pengunjung masuk</div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
                <Link href="/organizer/museums" class="bg-white p-4 rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-sm transition text-center">
                    <p class="text-sm font-semibold text-slate-700">Museum Saya</p>
                </Link>
                <Link href="/organizer/schedules" class="bg-white p-4 rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-sm transition text-center">
                    <p class="text-sm font-semibold text-slate-700">Tiket & Jadwal</p>
                </Link>
                <Link href="/organizer/scanner" class="bg-white p-4 rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-sm transition text-center">
                    <p class="text-sm font-semibold text-slate-700">Gate Scanner</p>
                </Link>
                <Link href="/organizer/reports" class="bg-white p-4 rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-sm transition text-center">
                    <p class="text-sm font-semibold text-slate-700">Laporan</p>
                </Link>
            </div>

            <!-- Recent Bookings -->
            <div v-if="recentBookings && recentBookings.length > 0" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="font-bold text-slate-900 text-sm">Booking Terkini</h3>
                    <Link href="/organizer/bookings" class="text-xs font-semibold text-blue-600 hover:underline">Lihat semua</Link>
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
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusBadge[b.status]">
                                        {{ b.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-900">
                                    {{ formatRupiah(b.total_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </OrganizerLayout>
</template>
