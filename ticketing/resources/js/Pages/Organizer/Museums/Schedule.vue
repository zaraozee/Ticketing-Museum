<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import OrganizerLayout from '../../../Layouts/OrganizerLayout.vue';

interface SlotRow {
    id: number;
    visit_date: string;
    label: string;
    capacity: number;
    reserved_count: number;
    sold_count: number;
    remaining: number;
    status: string;
    status_label: string;
    availability: string;
    availability_label: string;
}

interface TemplateRow {
    id: number;
    name: string;
    label: string;
    start_time: string | null;
    end_time: string | null;
    capacity: number;
    is_active: boolean;
}

interface OverrideRow {
    id: number;
    visit_date: string;
    start_time: string | null;
    capacity: number;
    note: string | null;
}

const props = defineProps<{
    museum: { slug: string; name: string; booking_mode: string; booking_mode_label: string };
    month: string;
    slots: SlotRow[];
    templates: TemplateRow[];
    overrides: OverrideRow[];
}>();

const isDaily = computed(() => props.museum.booking_mode === 'daily');

const monthLabel = computed(() => {
    const [year, month] = props.month.split('-');

    return new Date(Number(year), Number(month) - 1, 1).toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
});

const changeMonth = (offset: number): void => {
    const [year, month] = props.month.split('-').map(Number);
    const next = new Date(year, month - 1 + offset, 1);
    const value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;

    router.get(`/organizer/museums/${props.museum.slug}/schedule`, { month: value }, { preserveState: true });
};

// ---- Template slot ----
const templateForm = useForm({
    name: '',
    start_time: '' as string | null,
    end_time: '' as string | null,
    capacity: 50 as number | string,
    is_active: true,
});

const editingTemplateId = ref<number | null>(null);

const resetTemplateForm = (): void => {
    editingTemplateId.value = null;
    templateForm.reset();
    templateForm.clearErrors();
    templateForm.is_active = true;
};

const startEditTemplate = (row: TemplateRow): void => {
    editingTemplateId.value = row.id;
    templateForm.name = row.name;
    templateForm.start_time = row.start_time;
    templateForm.end_time = row.end_time;
    templateForm.capacity = row.capacity;
    templateForm.is_active = row.is_active;
    templateForm.clearErrors();
};

const submitTemplate = (): void => {
    if (editingTemplateId.value === null) {
        templateForm.post(`/organizer/museums/${props.museum.slug}/slot-templates`, {
            preserveScroll: true,
            onSuccess: () => resetTemplateForm(),
        });

        return;
    }

    templateForm.patch(`/organizer/museums/${props.museum.slug}/slot-templates/${editingTemplateId.value}`, {
        preserveScroll: true,
        onSuccess: () => resetTemplateForm(),
    });
};

const destroyTemplate = (row: TemplateRow): void => {
    if (!confirm(`Hapus template slot "${row.name}"? Slot yang sudah dibuat tetap ada.`)) {
        return;
    }

    templateForm.delete(`/organizer/museums/${props.museum.slug}/slot-templates/${row.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (editingTemplateId.value === row.id) {
                resetTemplateForm();
            }
        },
    });
};

// ---- Generate ----
const generate = (): void => {
    router.post(
        `/organizer/museums/${props.museum.slug}/schedule/generate?month=${props.month}`,
        {},
        { preserveScroll: true },
    );
};

// ---- Ubah kapasitas slot ----
const editingSlotId = ref<number | null>(null);
const slotCapacity = ref<number | string>(0);

const startEditSlot = (row: SlotRow): void => {
    editingSlotId.value = row.id;
    slotCapacity.value = row.capacity;
};

const saveSlotCapacity = (row: SlotRow): void => {
    router.patch(
        `/organizer/museums/${props.museum.slug}/schedule/slots/${row.id}`,
        { capacity: slotCapacity.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingSlotId.value = null;
            },
        },
    );
};

// ---- Override kuota ----
const overrideForm = useForm({
    visit_date: '',
    start_time: '' as string | null,
    capacity: 0 as number | string,
    note: '',
});

const submitOverride = (): void => {
    overrideForm.post(`/organizer/museums/${props.museum.slug}/slot-overrides`, {
        preserveScroll: true,
        onSuccess: () => {
            overrideForm.reset();
        },
    });
};

const destroyOverride = (row: OverrideRow): void => {
    if (!confirm(`Hapus override kuota tanggal ${row.visit_date}?`)) {
        return;
    }

    overrideForm.delete(`/organizer/museums/${props.museum.slug}/slot-overrides/${row.id}`, {
        preserveScroll: true,
    });
};

// ---- Pengelompokan slot per tanggal ----
const slotsByDate = computed(() => {
    const grouped: Record<string, SlotRow[]> = {};

    for (const slot of props.slots) {
        grouped[slot.visit_date] = grouped[slot.visit_date] ?? [];
        grouped[slot.visit_date].push(slot);
    }

    return Object.entries(grouped).map(([date, rows]) => ({ date, rows }));
});

const availabilityClass = (availability: string): string => {
    switch (availability) {
        case 'available':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'almost_full':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'full':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

const formatDate = (date: string): string =>
    new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
</script>

<template>
    <OrganizerLayout>
        <Head :title="`Jadwal & Kuota — ${museum.name}`" />

        <template #header>Jadwal &amp; Kuota</template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ museum.name }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Mode pemesanan: <span class="font-semibold">{{ museum.booking_mode_label }}</span>
                    <span v-if="isDaily"> · satu slot per hari</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="`/organizer/museums/${museum.slug}/tickets`"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Jenis Tiket
                </Link>
                <Link
                    href="/organizer/schedules"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>
        </div>

        <!-- Kalender -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="changeMonth(-1)"
                    >
                        ←
                    </button>
                    <span class="min-w-[160px] text-center text-sm font-bold capitalize text-slate-900">{{ monthLabel }}</span>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="changeMonth(1)"
                    >
                        →
                    </button>
                </div>

                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    @click="generate"
                >
                    Buat / Perbarui Slot Bulan Ini
                </button>
            </div>

            <div v-if="slotsByDate.length" class="divide-y divide-slate-100">
                <div v-for="group in slotsByDate" :key="group.date" class="p-4">
                    <div class="mb-2 text-sm font-bold capitalize text-slate-800">{{ formatDate(group.date) }}</div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="slot in group.rows"
                            :key="slot.id"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-900">{{ slot.label }}</span>
                                <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="availabilityClass(slot.availability)">
                                    {{ slot.availability_label }}
                                </span>
                            </div>

                            <div class="mt-2 flex items-baseline gap-1 text-xs text-slate-600">
                                <span class="text-lg font-bold text-slate-900">{{ slot.remaining }}</span>
                                <span>sisa dari {{ slot.capacity }}</span>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">
                                terjual {{ slot.sold_count }} · ditahan {{ slot.reserved_count }}
                            </div>

                            <div v-if="editingSlotId === slot.id" class="mt-2 flex gap-2">
                                <input
                                    v-model="slotCapacity"
                                    type="number"
                                    min="0"
                                    class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-sm"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700"
                                    @click="saveSlotCapacity(slot)"
                                >
                                    Simpan
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700"
                                    @click="editingSlotId = null"
                                >
                                    Batal
                                </button>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="mt-2 text-xs font-semibold text-blue-600 hover:underline"
                                @click="startEditSlot(slot)"
                            >
                                Ubah kuota
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-10 text-center text-sm text-slate-500">
                Belum ada slot pada bulan ini. Tambahkan template slot lalu tekan
                <span class="font-semibold">Buat / Perbarui Slot Bulan Ini</span>.
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Template slot -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    {{ editingTemplateId === null ? 'Tambah Template Slot' : 'Ubah Template Slot' }}
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Template adalah cetakan. Slot dibuat dari template ini setiap dini hari.
                </p>

                <form class="mt-4 space-y-3" @submit.prevent="submitTemplate">
                    <div>
                        <label for="template-name" class="block text-sm font-medium text-slate-700">Nama slot</label>
                        <input
                            id="template-name"
                            v-model="templateForm.name"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="Sesi Pagi"
                        />
                        <p v-if="templateForm.errors.name" class="mt-1 text-xs text-rose-600">{{ templateForm.errors.name }}</p>
                    </div>

                    <div v-if="!isDaily" class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-slate-700">Jam mulai</label>
                            <input
                                id="start_time"
                                v-model="templateForm.start_time"
                                type="time"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p v-if="templateForm.errors.start_time" class="mt-1 text-xs text-rose-600">{{ templateForm.errors.start_time }}</p>
                        </div>
                        <div>
                            <label for="end_time" class="block text-sm font-medium text-slate-700">Jam selesai</label>
                            <input
                                id="end_time"
                                v-model="templateForm.end_time"
                                type="time"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p v-if="templateForm.errors.end_time" class="mt-1 text-xs text-rose-600">{{ templateForm.errors.end_time }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="template-capacity" class="block text-sm font-medium text-slate-700">Kuota</label>
                        <input
                            id="template-capacity"
                            v-model="templateForm.capacity"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                        <p v-if="templateForm.errors.capacity" class="mt-1 text-xs text-rose-600">{{ templateForm.errors.capacity }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="templateForm.is_active" type="checkbox" class="rounded border-slate-300" />
                        Aktif
                    </label>

                    <div class="flex gap-2 pt-1">
                        <button
                            type="submit"
                            :disabled="templateForm.processing"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ editingTemplateId === null ? 'Tambah' : 'Simpan' }}
                        </button>
                        <button
                            v-if="editingTemplateId !== null"
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="resetTemplateForm"
                        >
                            Batal
                        </button>
                    </div>
                </form>

                <ul class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                    <li v-for="row in templates" :key="row.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-900">{{ row.name }}</div>
                            <div class="text-xs text-slate-500">{{ row.label }} · kuota {{ row.capacity }}</div>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            <span
                                class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                :class="row.is_active
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    : 'bg-slate-100 text-slate-600 border-slate-200'"
                            >
                                {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <button type="button" class="text-xs font-semibold text-blue-600 hover:underline" @click="startEditTemplate(row)">
                                Ubah
                            </button>
                            <button type="button" class="text-xs font-semibold text-rose-600 hover:underline" @click="destroyTemplate(row)">
                                Hapus
                            </button>
                        </div>
                    </li>
                    <li v-if="templates.length === 0" class="py-6 text-center text-sm text-slate-500">
                        Belum ada template slot.
                    </li>
                </ul>
            </div>

            <!-- Override kuota -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">Override Kuota per Tanggal</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Ubah kuota pada tanggal tertentu (mis. perbaikan ruang). Tidak ditimpa saat pembuatan slot.
                </p>

                <form class="mt-4 space-y-3" @submit.prevent="submitOverride">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="override-date" class="block text-sm font-medium text-slate-700">Tanggal</label>
                            <input
                                id="override-date"
                                v-model="overrideForm.visit_date"
                                type="date"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p v-if="overrideForm.errors.visit_date" class="mt-1 text-xs text-rose-600">{{ overrideForm.errors.visit_date }}</p>
                        </div>
                        <div>
                            <label for="override-capacity" class="block text-sm font-medium text-slate-700">Kuota</label>
                            <input
                                id="override-capacity"
                                v-model="overrideForm.capacity"
                                type="number"
                                min="0"
                                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p v-if="overrideForm.errors.capacity" class="mt-1 text-xs text-rose-600">{{ overrideForm.errors.capacity }}</p>
                        </div>
                    </div>

                    <div v-if="!isDaily">
                        <label for="override-start" class="block text-sm font-medium text-slate-700">Jam slot (kosongkan untuk semua)</label>
                        <input
                            id="override-start"
                            v-model="overrideForm.start_time"
                            type="time"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                        <p v-if="overrideForm.errors.start_time" class="mt-1 text-xs text-rose-600">{{ overrideForm.errors.start_time }}</p>
                    </div>

                    <div>
                        <label for="override-note" class="block text-sm font-medium text-slate-700">Catatan</label>
                        <input
                            id="override-note"
                            v-model="overrideForm.note"
                            type="text"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            placeholder="Perbaikan ruang pamer"
                        />
                        <p v-if="overrideForm.errors.note" class="mt-1 text-xs text-rose-600">{{ overrideForm.errors.note }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="overrideForm.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        Simpan Override
                    </button>
                </form>

                <ul class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                    <li v-for="row in overrides" :key="row.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900">
                                {{ formatDate(row.visit_date) }}
                                <span v-if="row.start_time" class="text-xs font-normal text-slate-500">· {{ row.start_time }}</span>
                            </div>
                            <div class="text-xs text-slate-500">
                                Kuota {{ row.capacity }}<span v-if="row.note"> · {{ row.note }}</span>
                            </div>
                        </div>
                        <button type="button" class="flex-shrink-0 text-xs font-semibold text-rose-600 hover:underline" @click="destroyOverride(row)">
                            Hapus
                        </button>
                    </li>
                    <li v-if="overrides.length === 0" class="py-6 text-center text-sm text-slate-500">
                        Belum ada override kuota.
                    </li>
                </ul>
            </div>
        </div>
    </OrganizerLayout>
</template>
