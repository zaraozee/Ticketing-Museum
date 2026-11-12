<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface AnomalyRow {
    id: number;
    booking_code: string;
    museum_name: string;
    user_name: string;
    user_email: string;
    gross_amount: number;
    status: string;
    needs_review: boolean;
    created_at: string;
    provider_order_id: string;
}

const props = defineProps<{
    anomalies: Paginated<AnomalyRow>;
}>();

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

function resolveAnomaly(anomaly: AnomalyRow, action: string) {
    const form = useForm({
        action: action,
        notes: '',
    });

    form.patch(`/admin/anomalies/${anomaly.id}/resolve`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout>
        <Head title="Antrean Anomali Pembayaran" />

        <div class="mx-auto max-w-6xl px-4 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Antrean Anomali Pembayaran</h1>
                <p class="mt-1 text-sm text-slate-600">Pembayaran yang perlu peninjauan manual (BR-23, BR-11)</p>
            </div>

            <div v-if="anomalies.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500">
                Tidak ada anomali saat ini.
            </div>

            <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[36rem]">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Booking</th>
                                <th class="px-4 py-3">Museum</th>
                                <th class="px-4 py-3">Pengguna</th>
                                <th class="px-4 py-3">Nominal</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="anomaly in anomalies.data" :key="anomaly.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <p class="font-mono font-semibold text-slate-900">{{ anomaly.booking_code }}</p>
                                    <p class="text-xs text-slate-500">{{ anomaly.provider_order_id }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ anomaly.museum_name }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">{{ anomaly.user_name }}</p>
                                    <p class="text-xs text-slate-500">{{ anomaly.user_email }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ formatRupiah(anomaly.gross_amount) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">
                                        {{ anomaly.needs_review ? 'Perlu Review' : anomaly.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ anomaly.created_at }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        @click="resolveAnomaly(anomaly, 'confirm_refund')"
                                        class="mr-2 text-sm font-semibold text-blue-600 hover:text-blue-700"
                                    >
                                        Refund
                                    </button>
                                    <button
                                        type="button"
                                        @click="resolveAnomaly(anomaly, 'mark_resolved')"
                                        class="text-sm font-semibold text-slate-600 hover:text-slate-700"
                                    >
                                        Selesai
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <nav v-if="anomalies.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <template v-for="(link, index) in anomalies.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        :class="
                            link.active
                                ? 'border-blue-500 bg-blue-50 font-semibold text-blue-600'
                                : 'border-slate-100 text-slate-700 hover:bg-slate-50'
                        "
                        v-html="link.label"
                    />
                    <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-400" v-html="link.label" />
                </template>
            </nav>
        </div>
    </AdminLayout>
</template>
