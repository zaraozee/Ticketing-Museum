<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrganizerLayout from '../../Layouts/OrganizerLayout.vue';

interface Profile {
    id: number;
    organization_name: string;
    contact_phone?: string | null;
    status: 'pending' | 'approved' | 'suspended';
}

defineProps<{
    profile?: Profile | null;
}>();
</script>

<template>
    <OrganizerLayout>
        <Head title="Organizer Dashboard" />

        <template #header>Dashboard Pengelola Museum</template>

        <!-- Status Notification Box -->
        <div v-if="profile?.status === 'pending'" class="mb-6 rounded-2xl bg-amber-50 border border-amber-200 p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold flex-shrink-0">
                    !
                </div>
                <div>
                    <h3 class="font-bold text-amber-900 text-base">Akun Pengelola Sedang Menunggu Verifikasi</h3>
                    <p class="text-sm text-amber-800 mt-1 leading-relaxed">
                        Pendaftaran organisasi <strong>{{ profile?.organization_name }}</strong> sedang dalam proses peninjauan oleh tim Admin Platform.
                        Setelah disetujui, Anda dapat mempublikasikan museum, membuat jenis tiket, mengatur kuota slot waktu, dan menggunakan Gate Scanner.
                    </p>
                </div>
            </div>
        </div>

        <div v-else-if="profile?.status === 'approved'" class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold flex-shrink-0">
                    ✓
                </div>
                <div>
                    <h3 class="font-bold text-emerald-900 text-base">Akun Terverifikasi</h3>
                    <p class="text-sm text-emerald-800 mt-1 leading-relaxed">
                        Organisasi Anda telah disetujui. Anda sekarang memiliki akses penuh untuk mengelola profil museum, jadwal operasional, dan tiket.
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Access Action Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base mb-1">Museum Saya</h3>
                    <p class="text-xs text-slate-500">Kelola foto, deskripsi, fasilitas, jam buka, dan titik peta museum Anda.</p>
                </div>
                <div class="mt-6">
                    <button class="w-full text-xs font-semibold py-2 px-3 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                        Kelola Museum (Fase 3)
                    </button>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base mb-1">Tiket & Jadwal Kuota</h3>
                    <p class="text-xs text-slate-500">Atur harga tiket, template slot kedatangan, dan kapasitas pengunjung.</p>
                </div>
                <div class="mt-6">
                    <button class="w-full text-xs font-semibold py-2 px-3 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                        Atur Jadwal (Fase 4)
                    </button>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base mb-1">Gate Scanner</h3>
                    <p class="text-xs text-slate-500">Pindai tiket QR pengunjung di gerbang masuk secara instan via kamera.</p>
                </div>
                <div class="mt-6">
                    <button class="w-full text-xs font-semibold py-2 px-3 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                        Buka Scanner (Fase 6)
                    </button>
                </div>
            </div>
        </div>
    </OrganizerLayout>
</template>
