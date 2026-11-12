<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrganizerLayout from '@/Layouts/OrganizerLayout.vue';
import { router } from '@inertiajs/vue3';

interface Museum { id: number; name: string; }
interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
interface BookingRow {
    id: number; code: string; museum_name: string; user_name: string;
    user_email: string; status: string; status_label: string;
    total_amount: number; visit_date: string; created_at: string;
}

const props = defineProps<{
    bookings: Paginated<BookingRow>;
    museums: Museum[];
    filters: { status?: string; museum_id?: number };
}>();

function filter(key: string, value: any) {
    router.get('/organizer/bookings', { ...props.filters, [key]: value }, { preserveScroll: true, preserveState: true });
}

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}
</script>

<template>
    <OrganizerLayout>
        <Head title="Daftar Pemesanan" />

        <div class="px-6 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Daftar Pemesanan</h1>
                <p class="text-sm text-slate-600">Booking di semua museum Anda</p>
            </div>

            <!-- Filter -->
            <div class="mb-4 flex flex-wrap gap-3">
                <select :value="filters.museum_id" @change="filter('museum_id', ($event.target as HTMLSelectElement).value)" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua Museum</option>
                    <option v-for="m in museums" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <select :value="filters.status" @change="filter('status', ($event.target as HTMLSelectElement).value)" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Semua Status</option>
                    <option value="pending_payment">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[36rem]">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Kode</th>
                                <th class="px-5 py-3">Museum</th>
                                <th class="px-5 py-3">Pemesan</th>
                                <th class="px-5 py-3">Kunjungan</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-slate-50">
                                <td class="px-5 py-3 font-mono font-semibold text-slate-900">{{ b.code }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ b.museum_name }}</td>
                                <td class="px-5 py-3 text-slate-700">{{ b.user_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ b.visit_date }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700">{{ b.status_label }}</span>
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ formatRupiah(b.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <nav v-if="bookings.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <template v-for="(link, index) in bookings.links" :key="index">
                    <Link v-if="link.url" :href="link.url" class="rounded-lg border px-3 py-1.5 text-sm" :class="link.active ? 'border-blue-500 bg-blue-50 font-semibold text-blue-600' : 'border-slate-100 text-slate-700 hover:bg-slate-50'" v-html="link.label" />
                    <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-400" v-html="link.label" />
                </template>
            </nav>
        </div>
    </OrganizerLayout>
</template>
