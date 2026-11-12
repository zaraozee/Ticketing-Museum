<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import OrganizerLayout from '@/Layouts/OrganizerLayout.vue';
import { router } from '@inertiajs/vue3';

interface Museum {
    id: number;
    name: string;
    slug: string;
}

interface ScanRow {
    id: number;
    result: string;
    label: string;
    color: string;
    scanned_at: string;
    scanned_by: string | null;
    ticket_code: string | null;
    holder: string | null;
    notes: string;
}

const props = defineProps<{
    museums: Museum[];
    scans: ScanRow[];
    selectedMuseum: number | null;
}>();

function selectMuseum(id: number) {
    router.get('/organizer/scans', { museum_id: id }, { preserveScroll: true });
}

const resultColors: Record<string, string> = {
    green: 'bg-emerald-100 text-emerald-800',
    red: 'bg-rose-100 text-rose-800',
    yellow: 'bg-amber-100 text-amber-800',
    gray: 'bg-slate-100 text-slate-700',
    orange: 'bg-orange-100 text-orange-800',
};
</script>

<template>
    <OrganizerLayout>
        <Head title="Riwayat Scan" />

        <div class="mx-auto max-w-4xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Riwayat Scan</h1>
                    <p class="mt-1 text-sm text-slate-600">100 scan terakhir per museum</p>
                </div>
                <Link
                    href="/organizer/scanner"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    ← Kembali ke Scanner
                </Link>
            </div>

            <!-- Museum Selector -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="museum in museums"
                    :key="museum.id"
                    type="button"
                    @click="selectMuseum(museum.id)"
                    class="rounded-lg border px-3 py-1.5 text-sm font-medium"
                    :class="
                        selectedMuseum === museum.id
                            ? 'border-blue-500 bg-blue-50 text-blue-600'
                            : 'border-slate-200 text-slate-700 hover:bg-slate-50'
                    "
                >
                    {{ museum.name }}
                </button>
            </div>

            <!-- Scans Table -->
            <div v-if="scans.length === 0" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Belum ada riwayat scan.
            </div>

            <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[36rem]">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Hasil</th>
                                <th class="px-4 py-3">Kode Tiket</th>
                                <th class="px-4 py-3">Pemesan</th>
                                <th class="px-4 py-3">Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="scan in scans" :key="scan.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-600">{{ scan.scanned_at }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="resultColors[scan.color]">
                                        {{ scan.label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-900">{{ scan.ticket_code || '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ scan.holder || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ scan.scanned_by || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </OrganizerLayout>
</template>
