<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface FacilityRow {
    id: number;
    name: string;
    icon: string | null;
    is_active: boolean;
    sort_order: number;
    museums_count: number;
}

defineProps<{ facilities: FacilityRow[] }>();

const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    icon: '',
    is_active: true,
    sort_order: 0,
});

const startEdit = (row: FacilityRow) => {
    editingId.value = row.id;
    form.name = row.name;
    form.icon = row.icon ?? '';
    form.is_active = row.is_active;
    form.sort_order = row.sort_order;
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/admin/facilities/${editingId.value}`, { preserveScroll: true, onSuccess: cancelEdit });
        return;
    }

    form.post('/admin/facilities', { preserveScroll: true, onSuccess: () => form.reset() });
};

const destroy = (row: FacilityRow) => {
    if (!confirm(`Hapus fasilitas "${row.name}"?`)) {
        return;
    }

    router.delete(`/admin/facilities/${row.id}`, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head title="Master Fasilitas" />

        <template #header>Master Fasilitas</template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">{{ editingId ? 'Ubah Fasilitas' : 'Tambah Fasilitas' }}</h2>

                <form class="mt-4 space-y-3" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Nama *</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Ikon (opsional)</label>
                        <input v-model="form.icon" type="text" placeholder="mis. parking" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Urutan</label>
                        <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                        Aktif
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60" :disabled="form.processing">
                            {{ editingId ? 'Simpan' : 'Tambah' }}
                        </button>
                        <button v-if="editingId" type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEdit">
                            Batal
                        </button>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Ikon</th>
                                <th class="px-4 py-3">Dipakai</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in facilities" :key="row.id" class="border-t border-slate-100">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ row.icon || '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ row.museums_count }} museum</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                        {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="text-xs font-semibold text-blue-600 hover:underline" @click="startEdit(row)">Ubah</button>
                                    <button type="button" class="ml-3 text-xs font-semibold text-rose-600 hover:underline" @click="destroy(row)">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="facilities.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada fasilitas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
