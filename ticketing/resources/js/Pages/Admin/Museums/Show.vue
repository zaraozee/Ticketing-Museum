<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface MuseumDetail {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    rejection_note: string | null;
    city: string | null;
    province: string | null;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    booking_mode: string;
    max_tickets_per_booking: number;
    category: string | null;
    organizer: string | null;
    organizer_email: string | null;
    submitted_at: string | null;
    images: { id: number; url: string; caption: string | null }[];
    facilities: string[];
    operating_hours: { day: string; open_time: string | null; close_time: string | null; is_closed: boolean }[];
    closed_dates: { closed_on: string; reason: string | null }[];
}

const props = defineProps<{
    museum: MuseumDetail;
    canReview: boolean;
    canSuspend: boolean;
    canRestore: boolean;
}>();

const form = useForm({ decision: '', note: '' });
const showNote = ref(false);

const decide = (decision: 'approve' | 'reject' | 'suspend' | 'restore') => {
    if ((decision === 'reject' || decision === 'suspend') && !showNote.value) {
        showNote.value = true;
        form.decision = decision;
        return;
    }

    form.decision = decision;
    form.patch(`/admin/museums/${props.museum.slug}/review`, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Tinjau ${museum.name}`" />

        <template #header>Tinjau Museum</template>

        <div class="mb-4">
            <Link href="/admin/museums" class="text-sm font-medium text-blue-600 hover:underline">← Kembali ke daftar verifikasi</Link>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-extrabold text-slate-900">{{ museum.name }}</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ museum.category || 'Tanpa kategori' }} · {{ museum.city || 'Kota belum diisi' }}
                            </p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ museum.status_label }}</span>
                    </div>

                    <p v-if="museum.rejection_note" class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Catatan: {{ museum.rejection_note }}
                    </p>

                    <div class="mt-5 space-y-4 text-sm">
                        <div>
                            <h3 class="font-bold text-slate-900">Deskripsi</h3>
                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ museum.description || 'Belum diisi.' }}</p>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900">Lokasi</h3>
                            <p class="mt-1 text-slate-700">{{ museum.address || '-' }}</p>
                            <p class="text-slate-600">{{ museum.city }}{{ museum.province ? `, ${museum.province}` : '' }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Koordinat: {{ museum.latitude ?? '—' }}, {{ museum.longitude ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900">Fasilitas</h3>
                            <p v-if="museum.facilities.length" class="mt-1 text-slate-700">{{ museum.facilities.join(', ') }}</p>
                            <p v-else class="mt-1 text-slate-500">Belum dipilih.</p>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900">Jam Operasional</h3>
                            <ul class="mt-1 space-y-0.5 text-slate-700">
                                <li v-for="hour in museum.operating_hours" :key="hour.day">
                                    <span class="inline-block w-20 font-medium text-slate-600">{{ hour.day }}</span>
                                    <span v-if="hour.is_closed" class="text-rose-600">Tutup</span>
                                    <span v-else>{{ hour.open_time }} – {{ hour.close_time }}</span>
                                </li>
                                <li v-if="museum.operating_hours.length === 0" class="text-slate-500">Belum diisi.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="font-bold text-slate-900">Galeri ({{ museum.images.length }} foto)</h3>
                    <div v-if="museum.images.length" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div v-for="image in museum.images" :key="image.id" class="overflow-hidden rounded-xl border border-slate-200">
                            <img :src="image.url" :alt="image.caption || museum.name" class="aspect-[4/3] w-full object-cover" />
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-rose-600">Belum ada foto — museum tidak memenuhi syarat verifikasi.</p>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900">Pengelola</h3>
                    <p class="mt-1 text-sm text-slate-700">{{ museum.organizer || '-' }}</p>
                    <p class="text-xs text-slate-500">{{ museum.organizer_email }}</p>

                    <dl class="mt-4 space-y-1 text-xs text-slate-600">
                        <div class="flex justify-between"><dt>Mode</dt><dd>{{ museum.booking_mode }}</dd></div>
                        <div class="flex justify-between"><dt>Maks. tiket</dt><dd>{{ museum.max_tickets_per_booking }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900">Keputusan</h3>

                    <div v-if="canReview" class="mt-3 space-y-2">
                        <button
                            type="button"
                            class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="form.processing"
                            @click="decide('approve')"
                        >
                            Setujui &amp; Tayangkan
                        </button>
                        <button
                            type="button"
                            class="w-full rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:opacity-60"
                            :disabled="form.processing"
                            @click="decide('reject')"
                        >
                            Tolak
                        </button>
                    </div>

                    <button
                        v-if="canSuspend"
                        type="button"
                        class="mt-2 w-full rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="decide('suspend')"
                    >
                        Tangguhkan
                    </button>

                    <button
                        v-if="canRestore"
                        type="button"
                        class="mt-2 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="decide('restore')"
                    >
                        Aktifkan Kembali
                    </button>

                    <div v-if="showNote" class="mt-3">
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Alasan *</label>
                        <textarea v-model="form.note" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.note" class="mt-1 text-xs text-rose-600">{{ form.errors.note }}</p>
                        <button
                            type="button"
                            class="mt-2 w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                            :disabled="form.processing"
                            @click="decide(form.decision as 'reject' | 'suspend')"
                        >
                            Kirim Keputusan
                        </button>
                    </div>

                    <p v-if="!canReview && !canSuspend && !canRestore" class="mt-2 text-xs text-slate-500">
                        Tidak ada aksi tersedia untuk status museum ini.
                    </p>
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
