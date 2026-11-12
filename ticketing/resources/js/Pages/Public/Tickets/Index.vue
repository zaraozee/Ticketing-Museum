<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import { listOfflineTickets, offlineStatusLabel, type OfflineTicket } from '@/composables/offlineTickets';

interface TicketRow {
    id: number;
    uuid: string;
    ticket_code: string;
    museum_name: string;
    visit_date: string;
    slot_label: string;
    status: string;
    status_label: string;
    booking_code: string;
}

interface Paginated<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    tickets: Paginated<TicketRow>;
}>();

const { online } = useOnlineStatus();
const offlineTickets = ref<OfflineTicket[]>([]);

onMounted(async () => {
    try {
        offlineTickets.value = await listOfflineTickets();
    } catch {
        offlineTickets.value = [];
    }
});

function badgeClass(status: string): string {
    const map: Record<string, string> = {
        active: 'bg-emerald-100 text-emerald-800',
        used: 'bg-slate-100 text-slate-700',
        void: 'bg-rose-100 text-rose-800',
        expired: 'bg-amber-100 text-amber-800',
    };
    return map[status] || 'bg-slate-100 text-slate-700';
}
</script>

<template>
    <AppLayout>
        <Head title="Tiket Saya" />

        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Tiket Saya</h1>
                <p class="mt-1 text-sm text-slate-600">E-ticket kunjungan museum Anda</p>
            </div>

            <!-- Saat offline, daftar server tidak dapat dimuat: arahkan ke tiket tersimpan. -->
            <div v-if="!online" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900" role="status">
                <p class="font-semibold">Anda sedang offline.</p>
                <p class="mt-1">
                    Daftar tiket terbaru tidak dapat dimuat. E-ticket yang pernah Anda buka tetap tersimpan di perangkat dan
                    dapat dibuka dari
                    <Link href="/offline/tickets" class="font-semibold underline">Tiket Tersimpan</Link>.
                </p>
            </div>

            <div v-if="tickets.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
                <p class="text-sm text-slate-500">Belum ada tiket. Pesan sekarang!</p>
                <Link href="/museums" class="mt-4 inline-block font-semibold text-blue-600 hover:underline">
                    Jelajahi Museum
                </Link>
            </div>

            <div v-else class="space-y-3">
                <Link
                    v-for="ticket in tickets.data"
                    :key="ticket.uuid"
                    :href="`/tickets/${ticket.uuid}`"
                    class="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-blue-300 hover:shadow-sm"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-sm font-semibold text-slate-900">{{ ticket.ticket_code }}</p>
                            <p class="mt-1 text-base font-semibold text-slate-800">{{ ticket.museum_name }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ ticket.visit_date }} · {{ ticket.slot_label }}</p>
                            <p class="mt-1 text-xs text-slate-500">Booking: {{ ticket.booking_code }}</p>
                        </div>
                        <span class="self-start rounded-full px-3 py-1 text-xs font-semibold" :class="badgeClass(ticket.status)">
                            {{ ticket.status_label }}
                        </span>
                    </div>
                </Link>
            </div>

            <nav v-if="tickets.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <template v-for="(link, index) in tickets.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-1.5 text-sm leading-normal"
                        :class="
                            link.active
                                ? 'border-blue-500 bg-blue-50 font-semibold text-blue-600'
                                : 'border-slate-100 text-slate-700 hover:border-slate-200 hover:bg-slate-50'
                        "
                        v-html="link.label"
                    />
                    <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-400" v-html="link.label" />
                </template>
            </nav>

            <!-- Tiket tersimpan di perangkat (SYSTEM_DESIGN §15.3) -->
            <section v-if="offlineTickets.length" class="mt-10">
                <h2 class="text-base font-bold text-slate-900">Tersimpan di Perangkat</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Dibuka tanpa jaringan. Status selalu berlabel "Tidak terverifikasi" — validitas ditentukan server saat scan.
                </p>

                <ul class="mt-3 space-y-2">
                    <li v-for="ticket in offlineTickets" :key="ticket.uuid">
                        <Link
                            :href="`/tickets/${ticket.uuid}`"
                            class="block rounded-xl border border-slate-200 bg-white p-4 transition hover:border-blue-300"
                        >
                            <p class="font-mono text-sm font-semibold text-slate-900">{{ ticket.ticket_code }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ ticket.museum_name }}</p>
                            <p class="mt-0.5 text-xs text-slate-600">{{ ticket.visit_date }} · {{ ticket.slot_label }}</p>
                            <p class="mt-1 text-xs font-medium text-amber-700">{{ offlineStatusLabel(ticket.cached_at) }}</p>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
