<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

interface PageRow {
    id: number;
    title: string;
    slug: string;
    type: string;
    type_label: string;
    is_published: boolean;
    sort_order: number;
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{ pages: PageRow[]; types: Option[] }>();

const editingId = ref<number | null>(null);

const form = useForm({
    title: '',
    slug: '',
    type: 'page',
    content: '',
    is_published: false,
    sort_order: 0,
});

const startEdit = (row: PageRow) => {
    editingId.value = row.id;
    form.title = row.title;
    form.slug = row.slug;
    form.type = row.type;
    form.content = '';
    form.is_published = row.is_published;
    form.sort_order = row.sort_order;
};

const cancelEdit = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/admin/content-pages/${editingId.value}`, { preserveScroll: true, onSuccess: cancelEdit });
        return;
    }

    form.post('/admin/content-pages', { preserveScroll: true, onSuccess: () => form.reset() });
};

const destroy = (row: PageRow) => {
    if (!confirm(`Hapus halaman "${row.title}"?`)) {
        return;
    }

    router.delete(`/admin/content-pages/${row.id}`, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout>
        <Head title="Konten & FAQ" />

        <template #header>Konten &amp; FAQ</template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">{{ editingId ? 'Ubah Halaman' : 'Tambah Halaman' }}</h2>

                <form class="mt-4 space-y-3" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Judul *</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">Jenis *</label>
                            <select v-model="form.type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <option v-for="type in props.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">Urutan</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Slug (opsional)</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Isi</label>
                        <textarea v-model="form.content" rows="8" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_published" type="checkbox" class="rounded border-slate-300" />
                        Tayangkan
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

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Judul</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in props.pages" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ row.title }}</p>
                                <p class="text-xs text-slate-500">/{{ row.slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.type_label }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="row.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                    {{ row.is_published ? 'Tayang' : 'Draf' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="text-xs font-semibold text-blue-600 hover:underline" @click="startEdit(row)">Ubah</button>
                                <button type="button" class="ml-3 text-xs font-semibold text-rose-600 hover:underline" @click="destroy(row)">Hapus</button>
                            </td>
                        </tr>
                        <tr v-if="props.pages.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-slate-500">Belum ada halaman konten.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
