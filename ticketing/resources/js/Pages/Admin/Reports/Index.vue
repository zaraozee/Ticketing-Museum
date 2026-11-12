<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';

interface Museum {
    id: number;
    name: string;
    slug: string;
}
interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
interface BookingRow {
    id: number;
    code: string;
    museum_name: string;
    user_name: string;
    user_email: string;
    total_amount: number;
    visit_date: string;
    created_at: string;
}
interface RevenueByMuseum {
    name: string;
    revenue: number;
}

const props = defineProps<{
    museums: Museum[];
    bookings: Paginated<BookingRow>;
    totalRevenue: number;
    totalBookings: number;
    revenueByMuseum: RevenueByMuseum[];
    startDate: string;
    endDate: string;
    selectedMuseum: number | null;
}>();

const localMuseum = ref(props.selectedMuseum);
const localStart = ref(props.startDate);
const localEnd = ref(props.endDate);

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

function exportCsv(): void {
    const params = new URLSearchParams({ start_date: localStart.value, end_date: localEnd.value });

    if (localMuseum.value) {
        params.set('museum_id', String(localMuseum.value));
    }

    window.location.href = `/admin/reports/export?${params.toString()}`;
}
</script>

<template>
    <AdminLayout>
        <Head title="Laporan Platform" />

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Laporan Platform</h1>
                <p class="text-sm text-slate-600">Transaksi lintas museum</p>
            </div>
            <button
                type="button"
                class="min-h-[44px] rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                @click="exportCsv"
            >
                Ekspor CSV
            </button>
        </div>

        <!-- Filter -->
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <label class="sr-only" for="admin-filter-museum">Museum</label>
            <select
                id="admin-filter-museum"
                v-model="localMuseum"
                class="min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-base sm:text-sm"
            >
                <option :value="null">Semua Museum</option>
                <option v-for="m in museums" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <label class="sr-only" for="admin-filter-start">Tanggal mulai</label>
            <input
                id="admin-filter-start"
                v-model="localStart"
                type="date"
                class="min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-base sm:text-sm"
            />
            <label class="sr-only" for="admin-filter-end">Tanggal akhir</label>
            <input
                id="admin-filter-end"
                v-model="localEnd"
                type="date"
                class="min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-base sm:text-sm"
            />
        </div>

        <!-- Summary -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">Total Pendapatan</div>
                <div class="mt-1 text-xl font-extrabold text-emerald-600">{{ formatRupiah(totalRevenue) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">Total Booking</div>
                <div class="mt-1 text-xl font-extrabold text-slate-900">{{ totalBookings }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">Rata-rata/Booking</div>
                <div class="mt-1 text-xl font-extrabold text-slate-900">
                    {{ formatRupiah(totalBookings > 0 ? Math.round(totalRevenue / totalBookings) : 0) }}
                </div>
            </div>
        </div>

        <!-- Revenue by Museum -->
        <div v-if="revenueByMuseum.length > 0" class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Pendapatan per Museum (Top 10)</h2>
            </div>
            <div class="space-y-3 p-5">
                <div v-for="m in revenueByMuseum" :key="m.name" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
                    <div class="truncate text-sm font-medium text-slate-700 sm:w-48">{{ m.name }}</div>
                    <div class="h-6 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="flex h-full items-center justify-end rounded-full bg-blue-500 px-2"
                            :style="{ width: Math.max(5, (m.revenue / revenueByMuseum[0].revenue) * 100) + '%' }"
                        >
                            <span class="text-xs font-semibold text-white">{{ formatRupiah(m.revenue) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Transaksi</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Kode</th>
                            <th class="px-5 py-3">Museum</th>
                            <th class="px-5 py-3">Pemesan</th>
                            <th class="px-5 py-3">Kunjungan</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3 font-mono font-semibold text-slate-900">{{ b.code }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ b.museum_name }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ b.user_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ b.visit_date }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-900">
                                {{ formatRupiah(b.total_amount) }}
                            </td>
                        </tr>
                        <tr v-if="bookings.data.length === 0">
                            <td colspan="5" class="px-5 py-8 text-center text-slate-500">Belum ada transaksi pada rentang ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
