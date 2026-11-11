<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import OrganizerLayout from '../../../Layouts/OrganizerLayout.vue';

interface Option {
    value: string;
    label: string;
}

interface OperatingHour {
    day_of_week: number;
    open_time: string | null;
    close_time: string | null;
    is_closed: boolean;
}

interface MuseumImageRow {
    id: number;
    url: string;
    caption: string | null;
    is_cover: boolean;
}

interface ClosedDateRow {
    id: number;
    closed_on: string;
    reason: string | null;
}

interface MuseumPayload {
    id: number;
    slug: string;
    name: string;
    category_id: number;
    description: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    latitude: string | null;
    longitude: string | null;
    timezone: string;
    booking_mode: string;
    max_tickets_per_booking: number;
    entry_early_minutes: number;
    entry_late_minutes: number;
    youtube_video_id: string | null;
    status: string;
    status_label: string;
    rejection_note: string | null;
    facility_ids: number[];
    operating_hours: OperatingHour[];
    images: MuseumImageRow[];
    closed_dates: ClosedDateRow[];
}

const props = defineProps<{
    museum: MuseumPayload | null;
    categories: Option[];
    facilities: Option[];
    canSubmit: boolean;
}>();

const DAYS = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
    { value: 0, label: 'Minggu' },
];

const isEdit = computed(() => props.museum !== null);

const defaultHours = (): OperatingHour[] =>
    DAYS.map((day) => ({
        day_of_week: day.value,
        open_time: '09:00',
        close_time: '17:00',
        is_closed: false,
    }));

const mergedHours = (): OperatingHour[] => {
    if (!props.museum) {
        return defaultHours();
    }

    return DAYS.map((day) => {
        const existing = props.museum?.operating_hours.find((hour) => hour.day_of_week === day.value);
        return (
            existing ?? {
                day_of_week: day.value,
                open_time: '09:00',
                close_time: '17:00',
                is_closed: false,
            }
        );
    });
};

const form = useForm({
    name: props.museum?.name ?? '',
    category_id: props.museum?.category_id ? String(props.museum.category_id) : '',
    description: props.museum?.description ?? '',
    address: props.museum?.address ?? '',
    city: props.museum?.city ?? '',
    province: props.museum?.province ?? '',
    latitude: props.museum?.latitude ?? '',
    longitude: props.museum?.longitude ?? '',
    timezone: props.museum?.timezone ?? 'Asia/Jakarta',
    booking_mode: props.museum?.booking_mode ?? 'daily',
    max_tickets_per_booking: props.museum?.max_tickets_per_booking ?? 10,
    entry_early_minutes: props.museum?.entry_early_minutes ?? 15,
    entry_late_minutes: props.museum?.entry_late_minutes ?? 30,
    youtube_url: props.museum?.youtube_video_id ?? '',
    facility_ids: (props.museum?.facility_ids ?? []).map(String),
    operating_hours: mergedHours(),
});

const activeTab = ref<'profil' | 'fasilitas' | 'jadwal' | 'galeri'>('profil');

const submit = () => {
    if (isEdit.value && props.museum) {
        form.patch(`/organizer/museums/${props.museum.slug}`, { preserveScroll: true });
        return;
    }

    form.post('/organizer/museums');
};

const submitForm = useForm({});

// `submission` adalah kunci error dari validasi kelengkapan pengajuan
// (MuseumPublishService), bukan field form — karena itu diambil dari error bag.
const submissionError = computed(
    () => (submitForm.errors as unknown as Record<string, string>).submission ?? '',
);

const submitForReview = () => {
    if (!props.museum) {
        return;
    }

    submitForm.post(`/organizer/museums/${props.museum.slug}/submit`, { preserveScroll: true });
};

// --- Galeri ---
const imageForm = useForm<{ image: File | null; caption: string; is_cover: boolean }>({
    image: null,
    caption: '',
    is_cover: false,
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    imageForm.image = target.files?.[0] ?? null;
};

const uploadImage = () => {
    if (!props.museum) {
        return;
    }

    imageForm.post(`/organizer/museums/${props.museum.slug}/images`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => imageForm.reset('image', 'caption', 'is_cover'),
    });
};

const setCover = (image: MuseumImageRow) => {
    if (!props.museum) {
        return;
    }

    router.patch(
        `/organizer/museums/${props.museum.slug}/images/${image.id}`,
        { is_cover: true, caption: image.caption },
        { preserveScroll: true },
    );
};

const deleteImage = (image: MuseumImageRow) => {
    if (!props.museum) {
        return;
    }

    if (!confirm('Hapus foto ini?')) {
        return;
    }

    router.delete(`/organizer/museums/${props.museum.slug}/images/${image.id}`, { preserveScroll: true });
};

// --- Tanggal tutup ---
const closedDateForm = useForm({ closed_on: '', reason: '' });

const addClosedDate = () => {
    if (!props.museum) {
        return;
    }

    closedDateForm.post(`/organizer/museums/${props.museum.slug}/closed-dates`, {
        preserveScroll: true,
        onSuccess: () => closedDateForm.reset(),
    });
};

const removeClosedDate = (row: ClosedDateRow) => {
    if (!props.museum) {
        return;
    }

    router.delete(`/organizer/museums/${props.museum.slug}/closed-dates/${row.id}`, { preserveScroll: true });
};

const toggleFacility = (value: string) => {
    const index = form.facility_ids.indexOf(value);

    if (index >= 0) {
        form.facility_ids.splice(index, 1);
    } else {
        form.facility_ids.push(value);
    }
};

const statusBadge = computed(() => {
    switch (props.museum?.status) {
        case 'published':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'pending_review':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'rejected':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
});
</script>

<template>
    <OrganizerLayout>
        <Head :title="isEdit ? `Kelola ${museum?.name}` : 'Tambah Museum'" />

        <template #header>{{ isEdit ? 'Kelola Museum' : 'Tambah Museum' }}</template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <Link href="/organizer/museums" class="text-sm font-medium text-blue-600 hover:underline">← Kembali</Link>
                <h2 class="mt-1 text-xl font-bold text-slate-900">
                    {{ isEdit ? museum?.name : 'Museum Baru' }}
                </h2>
                <p v-if="isEdit" class="mt-1 flex items-center gap-2 text-sm text-slate-600">
                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="statusBadge">
                        {{ museum?.status_label }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-if="isEdit && canSubmit"
                    type="button"
                    class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100"
                    @click="submitForReview"
                >
                    Ajukan Verifikasi
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                </button>
            </div>
        </div>

        <div v-if="museum?.rejection_note && museum.status === 'rejected'" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <strong>Perlu perbaikan:</strong> {{ museum.rejection_note }}
        </div>

        <div v-if="submissionError" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">Belum bisa diajukan verifikasi:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="problem in submissionError.split('|')" :key="problem">{{ problem.trim() }}</li>
            </ul>
        </div>

        <div class="mb-4 flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1">
            <button
                v-for="tab in [
                    { key: 'profil', label: 'Profil' },
                    { key: 'fasilitas', label: 'Fasilitas' },
                    { key: 'jadwal', label: 'Jam & Tanggal Tutup' },
                    { key: 'galeri', label: 'Galeri' },
                ]"
                :key="tab.key"
                type="button"
                class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                @click="activeTab = tab.key as typeof activeTab"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- TAB PROFIL -->
        <div v-show="activeTab === 'profil'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Nama museum *</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Kategori *</label>
                    <select v-model="form.category_id" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Pilih kategori</option>
                        <option v-for="category in categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                    </select>
                    <p v-if="form.errors.category_id" class="mt-1 text-xs text-rose-600">{{ form.errors.category_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Zona waktu</label>
                    <select v-model="form.timezone" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                        <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                        <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Deskripsi</label>
                    <textarea v-model="form.description" rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Alamat</label>
                    <input v-model="form.address" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Kota</label>
                    <input v-model="form.city" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Provinsi</label>
                    <input v-model="form.province" type="text" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Latitude</label>
                    <input v-model="form.latitude" type="text" inputmode="decimal" placeholder="-7.797068" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.latitude" class="mt-1 text-xs text-rose-600">{{ form.errors.latitude }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Longitude</label>
                    <input v-model="form.longitude" type="text" inputmode="decimal" placeholder="110.370529" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                    <p v-if="form.errors.longitude" class="mt-1 text-xs text-rose-600">{{ form.errors.longitude }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Mode pemesanan</label>
                    <select v-model="form.booking_mode" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="daily">Harian</option>
                        <option value="timed_slot">Slot waktu</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Maks. tiket per pemesanan</label>
                    <input v-model.number="form.max_tickets_per_booking" type="number" min="1" max="50" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Toleransi masuk lebih awal (menit)</label>
                    <input v-model.number="form.entry_early_minutes" type="number" min="0" max="180" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Toleransi terlambat (menit)</label>
                    <input v-model.number="form.entry_late_minutes" type="number" min="0" max="180" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Tautan video YouTube (opsional)</label>
                    <input v-model="form.youtube_url" type="url" placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-blue-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-slate-500">Hanya ID video yang disimpan, bukan HTML/iframe.</p>
                    <p v-if="form.errors.youtube_url" class="mt-1 text-xs text-rose-600">{{ form.errors.youtube_url }}</p>
                </div>
            </div>
        </div>

        <!-- TAB FASILITAS -->
        <div v-show="activeTab === 'fasilitas'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-900">Fasilitas Museum</h3>
            <p class="mt-1 text-sm text-slate-600">Pilih fasilitas yang tersedia di museum Anda.</p>
            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                <label
                    v-for="facility in facilities"
                    :key="facility.value"
                    class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition"
                    :class="form.facility_ids.includes(facility.value) ? 'border-blue-300 bg-blue-50 text-blue-800' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300"
                        :checked="form.facility_ids.includes(facility.value)"
                        @change="toggleFacility(facility.value)"
                    />
                    {{ facility.label }}
                </label>
            </div>
        </div>

        <!-- TAB JADWAL -->
        <div v-show="activeTab === 'jadwal'" class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">Jam Operasional</h3>
                <p class="mt-1 text-sm text-slate-600">Centang "Tutup" untuk hari tanpa kunjungan.</p>

                <div class="mt-4 space-y-2">
                    <div
                        v-for="hour in form.operating_hours"
                        :key="hour.day_of_week"
                        class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 px-3 py-2"
                    >
                        <span class="w-20 text-sm font-semibold text-slate-700">
                            {{ DAYS.find((day) => day.value === hour.day_of_week)?.label }}
                        </span>
                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                            <input v-model="hour.is_closed" type="checkbox" class="rounded border-slate-300" />
                            Tutup
                        </label>
                        <template v-if="!hour.is_closed">
                            <input v-model="hour.open_time" type="time" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                            <span class="text-slate-400">–</span>
                            <input v-model="hour.close_time" type="time" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                        </template>
                    </div>
                </div>
                <p v-if="form.errors['operating_hours.0.close_time']" class="mt-2 text-xs text-rose-600">
                    {{ form.errors['operating_hours.0.close_time'] }}
                </p>
            </div>

            <div v-if="isEdit" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">Tanggal Tutup</h3>
                <p class="mt-1 text-sm text-slate-600">Tambahkan tanggal museum tutup di luar jadwal rutin.</p>

                <form class="mt-4 flex flex-col gap-2 sm:flex-row" @submit.prevent="addClosedDate">
                    <input v-model="closedDateForm.closed_on" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                    <input v-model="closedDateForm.reason" type="text" placeholder="Alasan (opsional)" class="flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Tambah
                    </button>
                </form>
                <p v-if="closedDateForm.errors.closed_on" class="mt-1 text-xs text-rose-600">{{ closedDateForm.errors.closed_on }}</p>

                <ul class="mt-4 space-y-2">
                    <li
                        v-for="row in museum?.closed_dates ?? []"
                        :key="row.id"
                        class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-sm"
                    >
                        <span class="text-slate-700">{{ row.closed_on }} <span class="text-slate-400">— {{ row.reason || 'Tanpa alasan' }}</span></span>
                        <button type="button" class="text-xs font-semibold text-rose-600 hover:underline" @click="removeClosedDate(row)">Hapus</button>
                    </li>
                    <li v-if="!museum?.closed_dates.length" class="text-sm text-slate-500">Belum ada tanggal tutup khusus.</li>
                </ul>
            </div>
        </div>

        <!-- TAB GALERI -->
        <div v-show="activeTab === 'galeri'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-bold text-slate-900">Galeri Foto</h3>
            <p class="mt-1 text-sm text-slate-600">Format JPG, PNG, atau WebP. Maksimal 5 MB, minimal 400×300 piksel.</p>

            <div v-if="!isEdit" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                Simpan museum terlebih dahulu untuk mulai mengunggah foto.
            </div>

            <template v-else>
                <form class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="uploadImage">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Berkas gambar</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="onFileChange" />
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-semibold text-slate-700">Keterangan (opsional)</label>
                        <input v-model="imageForm.caption" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                        :disabled="imageForm.processing"
                    >
                        {{ imageForm.processing ? 'Mengunggah…' : 'Unggah' }}
                    </button>
                </form>
                <p v-if="imageForm.errors.image" class="mt-1 text-xs text-rose-600">{{ imageForm.errors.image }}</p>

                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="image in museum?.images ?? []"
                        :key="image.id"
                        class="overflow-hidden rounded-xl border border-slate-200"
                    >
                        <img :src="image.url" :alt="image.caption || museum?.name" class="aspect-[4/3] w-full object-cover" />
                        <div class="space-y-2 p-3">
                            <p class="truncate text-xs text-slate-600">{{ image.caption || 'Tanpa keterangan' }}</p>
                            <div class="flex items-center justify-between">
                                <span v-if="image.is_cover" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">FOTO UTAMA</span>
                                <button v-else type="button" class="text-xs font-semibold text-blue-600 hover:underline" @click="setCover(image)">
                                    Jadikan utama
                                </button>
                                <button type="button" class="text-xs font-semibold text-rose-600 hover:underline" @click="deleteImage(image)">Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="museum && museum.images.length === 0" class="mt-4 text-sm text-slate-500">
                    Belum ada foto. Museum wajib memiliki minimal satu foto sebelum diajukan verifikasi.
                </p>
            </template>
        </div>
    </OrganizerLayout>
</template>
