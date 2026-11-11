<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface MuseumRow {
    id: number;
    slug: string;
    name: string;
    city: string | null;
    status: string;
    status_label: string;
    category: string | null;
    organizer: string | null;
    submitted_at: string | null;
    cover_url: string | null;
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    museums: {
        data: MuseumRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: string };
    statuses: Option[];
}>();

const changeStatus = (status: string) => {
    router.get('/admin/museums', { status }, { preserveState: true, replace: true });
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};
</script>

<template>
    <AdminLayout>
        <Head title="Verifikasi Museum" />

        <template #header>Verifikasi Museum</template>

        <div class="mb-4 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1">
            <button
                v-for="status in statuses"
                :key="status.value"
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="filters.status === status.value ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                @click="changeStatus(status.value)"
            >
                {{ status.label }}
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Museum</th>
                        <th class="px-4 py-3">Organizer</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Diajukan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="museum in props.museums.data" :key="museum.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-14 flex-shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                    <img v-if="museum.cover_url" :src="museum.cover_url" :alt="museum.name" class="h-full w-full object-cover" />
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ museum.name }}</p>
                                    <p class="text-xs text-slate-500">{{ museum.city || '-' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ museum.organizer || '-' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ museum.category || '-' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(museum.submitted_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                {{ museum.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/admin/museums/${museum.slug}`"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Tinjau
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="props.museums.data.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">Tidak ada museum pada status ini.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="props.museums.links.length > 3" class="mt-6 flex flex-wrap items-center justify-center gap-1">
            <template v-for="(link, index) in props.museums.links" :key="index">
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
    </AdminLayout>
</template>
