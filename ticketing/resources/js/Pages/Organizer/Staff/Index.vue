<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import OrganizerLayout from '@/Layouts/OrganizerLayout.vue';
import { ref } from 'vue';

interface Museum {
    id: number;
    name: string;
    slug: string;
}

interface StaffRow {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    staff_role: string;
    is_active: boolean;
    created_at: string;
}

const props = defineProps<{
    museum: Museum;
    staff: StaffRow[];
}>();

const showAddForm = ref(false);

const form = useForm({
    email: '',
    staff_role: 'gate_staff',
    is_active: true,
});

function submitForm() {
    form.post(`/organizer/museums/${props.museum.slug}/staff`, {
        onSuccess: () => {
            form.reset();
            showAddForm.value = false;
        },
    });
}

function toggleActive(staff: StaffRow) {
    useForm({ is_active: !staff.is_active }).patch(
        `/organizer/museums/${props.museum.slug}/staff/${staff.id}`,
        { preserveScroll: true },
    );
}

function removeStaff(staff: StaffRow) {
    if (confirm(`Hapus ${staff.name} dari staf museum?`)) {
        useForm({}).delete(`/organizer/museums/${props.museum.slug}/staff/${staff.id}`, {
            preserveScroll: true,
        });
    }
}

const roleLabels: Record<string, string> = {
    manager: 'Manajer',
    gate_staff: 'Petugas Gate',
};
</script>

<template>
    <OrganizerLayout>
        <Head :title="`Staf - ${museum.name}`" />

        <div class="mx-auto max-w-4xl px-4 py-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Kelola Staf</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ museum.name }}</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        href="/organizer/scanner"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Gate Scanner
                    </Link>
                    <button
                        type="button"
                        @click="showAddForm = !showAddForm"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        + Tambah Staf
                    </button>
                </div>
            </div>

            <!-- Add Form -->
            <div v-if="showAddForm" class="mb-6 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">Tambah Staf Baru</h2>
                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Email Pengguna</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="email@contoh.com"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-rose-600">{{ form.errors.email }}</p>
                        <p class="mt-1 text-xs text-slate-500">Pengguna harus sudah terdaftar di platform.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Peran</label>
                        <select
                            v-model="form.staff_role"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none"
                        >
                            <option value="gate_staff">Petugas Gate (Scanner)</option>
                            <option value="manager">Manajer</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Simpan
                        </button>
                        <button
                            type="button"
                            @click="showAddForm = false"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>

            <!-- Staff List -->
            <div v-if="staff.length === 0" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Belum ada staf. Klik "Tambah Staf" untuk menambahkan.
            </div>

            <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[36rem]">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Peran</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="member in staff" :key="member.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ member.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ member.email }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        {{ roleLabels[member.staff_role] || member.staff_role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        @click="toggleActive(member)"
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="member.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ member.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        @click="removeStaff(member)"
                                        class="text-sm font-semibold text-rose-600 hover:text-rose-700"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </OrganizerLayout>
</template>
