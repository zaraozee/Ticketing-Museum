<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import OrganizerLayout from '../../../Layouts/OrganizerLayout.vue';

interface TicketTypeRow {
    id: number;
    name: string;
    description: string | null;
    price: number;
    price_label: string;
    min_age: number | null;
    max_age: number | null;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{
    museum: { slug: string; name: string; status: string; status_label: string };
    ticketTypes: TicketTypeRow[];
}>();

const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    description: '' as string | null,
    price: 0 as number | string,
    min_age: '' as number | string | null,
    max_age: '' as number | string | null,
    is_active: true,
    sort_order: '' as number | string | null,
});

const resetForm = (): void => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
};

const startEdit = (row: TicketTypeRow): void => {
    editingId.value = row.id;
    form.name = row.name;
    form.description = row.description;
    form.price = row.price;
    form.min_age = row.min_age;
    form.max_age = row.max_age;
    form.is_active = row.is_active;
    form.sort_order = row.sort_order;
    form.clearErrors();
};

const submit = (): void => {
    if (editingId.value === null) {
        form.post(`/organizer/museums/${props.museum.slug}/ticket-types`, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });

        return;
    }

    form.patch(`/organizer/museums/${props.museum.slug}/ticket-types/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
};

const destroy = (row: TicketTypeRow): void => {
    if (!confirm(`Hapus jenis tiket "${row.name}"? Booking lama tidak terpengaruh.`)) {
        return;
    }

    form.delete(`/organizer/museums/${props.museum.slug}/ticket-types/${row.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingId.value === row.id) {
                resetForm();
            }
        },
    });
};
</script>

<template>
    <OrganizerLayout>
        <Head :title="`Jenis Tiket — ${museum.name}`" />

        <template #header>Jenis Tiket</template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ museum.name }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Harga disimpan sebagai rupiah bulat. Perubahan harga tidak mengubah booking lama.
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="`/organizer/museums/${museum.slug}/schedule`"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Jadwal &amp; Kuota
                </Link>
                <Link
                    href="/organizer/schedules"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Form -->
            <div class="lg:col-span-2">
                <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ editingId === null ? 'Tambah Jenis Tiket' : 'Ubah Jenis Tiket' }}
                    </h3>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-slate-700">Nama</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder="Dewasa"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="price" class="block text-sm font-medium text-slate-700">Harga (Rp)</label>
                            <input
                                id="price"
                                v-model="form.price"
                                type="number"
                                min="0"
                                step="500"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            />
                            <p v-if="form.errors.price" class="mt-1 text-xs text-rose-600">{{ form.errors.price }}</p>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-slate-700">Keterangan</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="2"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder="Berlaku untuk pengunjung umum"
                            />
                            <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="min_age" class="block text-sm font-medium text-slate-700">Usia min.</label>
                                <input
                                    id="min_age"
                                    v-model="form.min_age"
                                    type="number"
                                    min="0"
                                    max="120"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.min_age" class="mt-1 text-xs text-rose-600">{{ form.errors.min_age }}</p>
                            </div>
                            <div>
                                <label for="max_age" class="block text-sm font-medium text-slate-700">Usia maks.</label>
                                <input
                                    id="max_age"
                                    v-model="form.max_age"
                                    type="number"
                                    min="0"
                                    max="120"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.max_age" class="mt-1 text-xs text-rose-600">{{ form.errors.max_age }}</p>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                            Aktif dan dapat dipesan
                        </label>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ editingId === null ? 'Tambah' : 'Simpan' }}
                        </button>
                        <button
                            v-if="editingId !== null"
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="resetForm"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="lg:col-span-3">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Harga</th>
                                <th class="px-4 py-3">Usia</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in ticketTypes" :key="row.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900">{{ row.name }}</div>
                                    <div v-if="row.description" class="text-xs text-slate-500">{{ row.description }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ row.price_label }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <span v-if="row.min_age === null && row.max_age === null">—</span>
                                    <span v-else>{{ row.min_age ?? 0 }}–{{ row.max_age ?? '∞' }} th</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                        :class="row.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-slate-100 text-slate-600 border-slate-200'"
                                    >
                                        {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="text-xs font-semibold text-blue-600 hover:underline" @click="startEdit(row)">
                                        Ubah
                                    </button>
                                    <button type="button" class="ml-3 text-xs font-semibold text-rose-600 hover:underline" @click="destroy(row)">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="ticketTypes.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Belum ada jenis tiket. Tambahkan minimal satu agar museum dapat dijual.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </OrganizerLayout>
</template>
