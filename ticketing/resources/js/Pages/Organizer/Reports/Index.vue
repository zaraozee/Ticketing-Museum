<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import OrganizerLayout from '@/Layouts/OrganizerLayout.vue';
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
    user_name: string;
    user_email: string;
    status: string;
    total_amount: number;
    visit_date: string;
    created_at: string;
}
interface VisitorRow {
    id: number;
    code: string;
    status: string;
    visitor_name: string;
    visitor_email: string;
    ticket_type: string;
    visit_date: string;
    used_at: string | null;
}

const props = defineProps<{
    museums: Museum[];
    selectedMuseum: number | null;
    startDate: string;
    endDate: string;
    bookings: Paginated<BookingRow>;
    visitors: VisitorRow[];
    revenue: number;
    ticketCount: number;
}>();

const localMuseum = ref(props.selectedMuseum);
const localStart = ref(props.startDate);
const localEnd = ref(props.endDate);

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

function applyFilter(): void {
    router.get(
        '/organizer/reports',
        {
            museum_id: localMuseum.value,
            start_date: localStart.value,
            end_date: localEnd.value,
        },
        { preserveScroll: true },
    );
}

function exportCsv(): void {
    const params = new URLSearchParams({
        museum_id: String(localMuseum.value),
        start_date: localStart.value,
        end_date: localEnd.value,
    });
    window.location.href = `/organizer/reports/export?${params}`;
}
</script>

<template>
    <OrganizerLayout>
        <Head title="Laporan" />

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Laporan Penjualan</h1>
                <p class="text-sm text-slate-600">Filter dan ekspor data transaksi</p>
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
            <label class="sr-only" for="filter-museum">Museum</label>
            <select
                id="filter-museum"
                v-model="localMuseum"
                class="min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-base sm:text-sm"
                @change="applyFilter"
            >
                <option v-for="m in museums" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <label class="sr-only" for="filter-start">Tanggal mulai</label>
            <input
                id="filter-start"
                v-model="localStart"
                type="date"
                class="min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-base sm:text-sm"
                @change="applyFilter"
            />
            <label class="sr-only" for="filter-end">Tanggal akhir</label>
            <input
                id="filter-end"
                v-model="localEnd"
                type="date"
                class="min-h-[44px] rounded-xl border border-slate-300 px-3 py-2 text-base sm:text-sm"
                @change="applyFilter"
            />
        </div>

        <!-- Summary -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">Total Pendapatan</div>
                <div class="mt-1 text-xl font-extrabold text-emerald-600">{{ formatRupiah(revenue) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">Tiket Terjual</div>
                <div class="mt-1 text-xl font-extrabold text-slate-900">{{ ticketCount }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">Booking Confirmed</div>
                <div class="mt-1 text-xl font-extrabold text-slate-900">{{ bookings.data.length }}</div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Transaksi</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Kode</th>
                            <th class="px-5 py-3">Pemesan</th>
                            <th class="px-5 py-3">Kunjungan</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3 font-mono font-semibold text-slate-900">{{ b.code }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ b.user_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ b.visit_date }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-900">
                                {{ formatRupiah(b.total_amount) }}
                            </td>
                        </tr>
                        <tr v-if="bookings.data.length === 0">
                            <td colspan="4" class="px-5 py-8 text-center text-slate-500">Belum ada transaksi pada rentang ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Visitors -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Daftar Pengunjung ({{ visitors.length }})</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Kode Tiket</th>
                            <th class="px-5 py-3">Nama</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Kunjungan</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="v in visitors" :key="v.id" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-slate-900">{{ v.code }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ v.visitor_name }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ v.ticket_type }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ v.visit_date }}</td>
                            <td class="px-5 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="v.status === 'used' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ v.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="visitors.length === 0">
                            <td colspan="5" class="px-5 py-8 text-center text-slate-500">Belum ada data pengunjung.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </OrganizerLayout>
</template>
