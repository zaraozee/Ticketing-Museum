<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrganizerLayout from '../../../Layouts/OrganizerLayout.vue';

interface MuseumRow {
    slug: string;
    name: string;
    status: string;
    status_label: string;
    booking_mode: string;
    booking_mode_label: string;
    active_ticket_types_count: number;
    active_slot_templates_count: number;
    upcoming_slot_count: number;
    upcoming_remaining: number;
}

defineProps<{
    museums: MuseumRow[];
}>();

const statusClass = (status: string): string => {
    switch (status) {
        case 'published':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'pending_review':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'rejected':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'suspended':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};
</script>

<template>
    <OrganizerLayout>
        <Head title="Tiket & Jadwal Kuota" />

        <template #header>Tiket &amp; Jadwal Kuota</template>

        <div class="mb-6">
            <h2 class="text-xl font-bold text-slate-900">Inventori yang Dapat Dijual</h2>
            <p class="mt-1 text-sm text-slate-600">
                Kelola jenis tiket, template slot, dan kuota kunjungan per museum. Pilih museum untuk mulai mengatur.
            </p>
        </div>

        <div v-if="museums.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="museum in museums"
                :key="museum.slug"
                class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-base font-bold text-slate-900">{{ museum.name }}</h3>
                    <span class="flex-shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(museum.status)">
                        {{ museum.status_label }}
                    </span>
                </div>

                <p class="mt-1 text-xs text-slate-500">Mode pemesanan: {{ museum.booking_mode_label }}</p>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">Jenis tiket aktif</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ museum.active_ticket_types_count }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">Template slot</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ museum.active_slot_templates_count }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">Slot mendatang</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ museum.upcoming_slot_count }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">Sisa kuota</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ museum.upcoming_remaining }}</dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-2">
                    <Link
                        :href="`/organizer/museums/${museum.slug}/tickets`"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Jenis Tiket
                    </Link>
                    <Link
                        :href="`/organizer/museums/${museum.slug}/schedule`"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        Jadwal &amp; Kuota
                    </Link>
                </div>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-slate-500">Belum ada museum yang dapat dikelola.</p>
            <Link href="/organizer/museums" class="mt-3 inline-block text-sm font-semibold text-blue-600 hover:underline">
                Tambah museum terlebih dahulu
            </Link>
        </div>
    </OrganizerLayout>
</template>
