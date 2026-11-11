<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrganizerLayout from '../../../Layouts/OrganizerLayout.vue';

interface MuseumRow {
    id: number;
    uuid: string;
    slug: string;
    name: string;
    city: string | null;
    status: string;
    status_label: string;
    category: string | null;
    images_count: number;
    cover_url: string | null;
    rejection_note: string | null;
}

defineProps<{
    museums: {
        data: MuseumRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    canCreate: boolean;
}>();

const badgeClass = (status: string): string => {
    switch (status) {
        case 'published':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'pending_review':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'rejected':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'suspended':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        case 'archived':
            return 'bg-slate-100 text-slate-600 border-slate-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};
</script>

<template>
    <OrganizerLayout>
        <Head title="Museum Saya" />

        <template #header>Museum Saya</template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Daftar Museum</h2>
                <p class="mt-1 text-sm text-slate-600">Kelola profil, galeri, jam operasional, dan pengajuan verifikasi museum.</p>
            </div>
            <Link
                v-if="canCreate"
                href="/organizer/museums/create"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
                + Tambah Museum
            </Link>
            <div v-else class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
                Akun organizer perlu diverifikasi Admin sebelum dapat menambah museum.
            </div>
        </div>

        <div v-if="museums.data.length" class="space-y-3">
            <div
                v-for="museum in museums.data"
                :key="museum.id"
                class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center"
            >
                <div class="h-24 w-full flex-shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:w-36">
                    <img
                        v-if="museum.cover_url"
                        :src="museum.cover_url"
                        :alt="museum.name"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full items-center justify-center text-xs text-slate-400">Tanpa foto</div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="truncate text-base font-bold text-slate-900">{{ museum.name }}</h3>
                        <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="badgeClass(museum.status)">
                            {{ museum.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ museum.category || 'Tanpa kategori' }} · {{ museum.city || 'Kota belum diisi' }} · {{ museum.images_count }} foto
                    </p>
                    <p v-if="museum.rejection_note" class="mt-2 rounded-lg bg-rose-50 px-3 py-1.5 text-xs text-rose-700">
                        Catatan Admin: {{ museum.rejection_note }}
                    </p>
                </div>

                <div class="flex flex-shrink-0 gap-2">
                    <Link
                        :href="`/organizer/museums/${museum.slug}/edit`"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Kelola
                    </Link>
                    <Link
                        v-if="museum.status === 'published'"
                        :href="`/museums/${museum.slug}`"
                        class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                    >
                        Lihat
                    </Link>
                </div>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
            <p class="text-slate-500">Belum ada museum. Tambahkan museum pertama Anda untuk mulai.</p>
        </div>

        <nav v-if="museums.links.length > 3" class="mt-8 flex flex-wrap items-center justify-center gap-1">
            <template v-for="(link, index) in museums.links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="link.active ? 'bg-blue-600 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    preserve-scroll
                    v-html="link.label"
                />
                <span v-else class="rounded-lg px-3 py-2 text-sm text-slate-400" v-html="link.label" />
            </template>
        </nav>
    </OrganizerLayout>
</template>
