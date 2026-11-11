<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface CategoryRow {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    sort_order: number;
    museums_count: number;
}

defineProps<{ categories: CategoryRow[] }>();

const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    slug: '',
    parent_id: null as number | null,
    is_active: true,
    sort_order: 0,
});

const startEdit = (row: CategoryRow) => {
    editingId.value = row.id;
    form.name = row.name;
    form.slug = row.slug;
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
        form.put(`/admin/categories/${editingId.value}`, { preserveScroll: true, onSuccess: cancelEdit });
        return;
    }

    form.post('/admin/categories', { preserveScroll: true, onSuccess: () => form.reset() });
};

const destroy = (row: CategoryRow) => {
    if (!confirm(`Hapus kategori "${row.name}"?`)) {
        return;
    }

    router.delete(`/admin/categories/${row.id}`, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head title="Kategori Museum" />

        <template #header>Kategori Museum</template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">{{ editingId ? 'Ubah Kategori' : 'Tambah Kategori' }}</h2>

                <form class="mt-4 space-y-3" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Nama *</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Slug (opsional)</label>
                        <input v-model="form.slug" type="text" placeholder="otomatis dari nama" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-rose-600">{{ form.errors.slug }}</p>
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
                                <th class="px-4 py-3">Slug</th>
                                <th class="px-4 py-3">Museum</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in categories" :key="row.id" class="border-t border-slate-100">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ row.slug }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ row.museums_count }}</td>
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
                            <tr v-if="categories.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada kategori.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
