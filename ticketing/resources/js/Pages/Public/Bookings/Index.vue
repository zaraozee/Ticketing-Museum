<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

interface BookingRow {
    uuid: string;
    booking_code: string;
    museum_name: string;
    visit_date: string;
    status: string;
    status_label: string;
    total_amount_label: string;
    created_at: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    bookings: Paginated<BookingRow>;
}>();

const badgeClass = (status: string): string => {
    switch (status) {
        case 'pending_payment':
            return 'bg-amber-100 text-amber-800';
        case 'confirmed':
            return 'bg-emerald-100 text-emerald-800';
        case 'refunded':
            return 'bg-rose-100 text-rose-800';
        default:
            return 'bg-slate-100 text-slate-700';
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Riwayat Pemesanan" />

        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-slate-900">Riwayat Pemesanan</h1>
        <p class="mt-1 text-sm text-slate-600">Semua pemesanan tiket Anda.</p>

        <p v-if="bookings.data.length === 0" class="mt-6 rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            Belum ada pemesanan.
            <Link href="/museums" class="font-semibold text-blue-600 hover:underline">Jelajah museum</Link>
        </p>

        <div v-else class="mt-6 space-y-3">
            <Link
                v-for="booking in bookings.data"
                :key="booking.uuid"
                :href="`/bookings/${booking.uuid}`"
                class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-300"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-mono text-sm font-semibold text-slate-900">{{ booking.booking_code }}</p>
                        <p class="text-sm text-slate-700">{{ booking.museum_name }}</p>
                        <p class="text-xs text-slate-500">{{ booking.visit_date }}</p>
                    </div>
                    <div class="text-right">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="badgeClass(booking.status)">
                            {{ booking.status_label }}
                        </span>
                        <p class="mt-2 text-sm font-bold text-slate-900">{{ booking.total_amount_label }}</p>
                    </div>
                </div>
            </Link>
        </div>

        <nav v-if="bookings.links.length > 3" class="mt-6 flex flex-wrap gap-1">
            <template v-for="(link, index) in bookings.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-lg border px-3 py-1.5 text-sm"
                    :class="link.active ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600'"
                    v-html="link.label"
                />
                <span v-else class="rounded-lg border border-slate-100 px-3 py-1.5 text-sm text-slate-400" v-html="link.label" />
            </template>
        </nav>
        </div>
    </AppLayout>
</template>
