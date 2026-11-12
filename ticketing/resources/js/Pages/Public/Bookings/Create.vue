<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

interface TicketTypeRow {
    id: number;
    name: string;
    price: number;
    price_label: string;
}

interface SlotRow {
    id: number;
    label: string;
    start_time: string | null;
    end_time: string | null;
    remaining: number;
    status: string;
    status_label: string;
    bookable: boolean;
}

interface DayRow {
    date: string;
    status: string;
    status_label: string;
    remaining: number;
    capacity?: number;
}

const props = defineProps<{
    museum: {
        slug: string;
        name: string;
        booking_mode: string;
        max_tickets_per_booking: number;
    };
    ticketTypes: TicketTypeRow[];
    contact: { name: string | null; email: string | null; phone: string | null };
}>();

const today = new Date();
const month = ref(
    `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`,
);
const days = ref<DayRow[]>([]);
const selectedDate = ref<string | null>(null);
const slots = ref<SlotRow[]>([]);
const loadingDays = ref(false);
const loadingSlots = ref(false);

const form = useForm({
    visit_slot_id: '' as number | string,
    contact_name: props.contact.name ?? '',
    contact_email: props.contact.email ?? '',
    contact_phone: props.contact.phone ?? '',
    quantities: {} as Record<number, number>,
});

const selectedSlot = computed<SlotRow | null>(
    () => slots.value.find((slot) => slot.id === Number(form.visit_slot_id)) ?? null,
);

const totalQuantity = computed<number>(() =>
    Object.values(form.quantities).reduce((sum, qty) => sum + (Number(qty) || 0), 0),
);

const totalAmount = computed<number>(() =>
    props.ticketTypes.reduce(
        (sum, type) => sum + type.price * (Number(form.quantities[type.id]) || 0),
        0,
    ),
);

const totalAmountLabel = computed<string>(
    () => `Rp ${totalAmount.value.toLocaleString('id-ID')}`,
);

const exceedsLimit = computed<boolean>(
    () => totalQuantity.value > props.museum.max_tickets_per_booking,
);

/**
 * Pesan galat untuk bagian pemilihan jumlah tiket. Kunci galat Laravel berbentuk
 * `items` atau `items.{index}.quantity`, di luar tipe data form sehingga dibaca
 * lewat record longgar.
 */
const quantitiesError = computed<string | null>(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.items ?? errors['items.0.quantity'] ?? errors['items.0.ticket_type_id'] ?? null;
});

const canSubmit = computed<boolean>(
    () =>
        form.visit_slot_id !== '' &&
        totalQuantity.value > 0 &&
        !exceedsLimit.value &&
        selectedSlot.value !== null &&
        selectedSlot.value.bookable,
);

const loadDays = async (): Promise<void> => {
    loadingDays.value = true;
    try {
        const response = await fetch(
            `/api/v1/museums/${props.museum.slug}/availability?month=${month.value}`,
            { headers: { Accept: 'application/json' } },
        );
        const payload = await response.json();
        days.value = payload.days ?? [];
    } finally {
        loadingDays.value = false;
    }
};

const selectDate = async (day: DayRow): Promise<void> => {
    // Tanggal tutup/penuh/belum dibuka tidak dapat dipilih (FR-BKG-02).
    if (day.status !== 'available' && day.status !== 'almost_full') {
        return;
    }

    selectedDate.value = day.date;
    form.visit_slot_id = '';
    loadingSlots.value = true;

    try {
        const response = await fetch(
            `/api/v1/museums/${props.museum.slug}/slots?date=${day.date}`,
            { headers: { Accept: 'application/json' } },
        );
        const payload = await response.json();
        slots.value = payload.slots ?? [];
    } finally {
        loadingSlots.value = false;
    }
};

const clampQuantity = (typeId: number, value: string): void => {
    const parsed = Math.max(0, Math.min(50, Number(value) || 0));

    // Sisa kuota slot membatasi jumlah yang dapat dipilih (FR-BKG-04).
    const capped = selectedSlot.value === null
        ? parsed
        : Math.min(parsed, selectedSlot.value.remaining);

    form.quantities = { ...form.quantities, [typeId]: capped };
};

const submit = (): void => {
    const items = Object.entries(form.quantities)
        .filter(([, qty]) => Number(qty) > 0)
        .map(([id, qty]) => ({ ticket_type_id: Number(id), quantity: Number(qty) }));

    form.transform((data) => ({
        visit_slot_id: data.visit_slot_id,
        contact_name: data.contact_name,
        contact_email: data.contact_email,
        contact_phone: data.contact_phone,
        items,
    })).post(`/museums/${props.museum.slug}/book`);
};

onMounted(loadDays);
</script>

<template>
    <AppLayout>
        <Head :title="`Pesan Tiket — ${museum.name}`" />

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <nav class="mb-4 text-sm text-slate-500">
                <Link href="/museums" class="hover:text-blue-600">Jelajah Museum</Link>
                <span class="mx-2">/</span>
                <Link :href="`/museums/${museum.slug}`" class="hover:text-blue-600">{{ museum.name }}</Link>
                <span class="mx-2">/</span>
                <span class="text-slate-700">Pesan Tiket</span>
            </nav>

            <h1 class="text-2xl font-bold text-slate-900">Pesan Tiket</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ museum.name }} · maksimal {{ museum.max_tickets_per_booking }} tiket per pemesanan
            </p>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <!-- Langkah 1: tanggal -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-base font-bold text-slate-900">1. Pilih Tanggal</h2>
                            <input
                                v-model="month"
                                type="month"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                                @change="loadDays"
                            />
                        </div>

                        <p v-if="loadingDays" class="mt-4 text-sm text-slate-500">Memuat ketersediaan…</p>

                        <div v-else class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5">
                            <button
                                v-for="day in days"
                                :key="day.date"
                                type="button"
                                class="rounded-lg border px-2 py-2 text-center text-xs transition"
                                :class="[
                                    selectedDate === day.date
                                        ? 'border-blue-600 bg-blue-50 text-blue-700'
                                        : 'border-slate-200 text-slate-700',
                                    day.status === 'available' || day.status === 'almost_full'
                                        ? 'hover:border-blue-400'
                                        : 'cursor-not-allowed opacity-50',
                                ]"
                                :disabled="day.status !== 'available' && day.status !== 'almost_full'"
                                @click="selectDate(day)"
                            >
                                <span class="block font-semibold">{{ day.date.slice(8, 10) }}</span>
                                <span class="block text-[10px]">{{ day.status_label }}</span>
                            </button>
                        </div>
                    </section>

                    <!-- Langkah 2: slot -->
                    <section
                        v-if="selectedDate !== null"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-base font-bold text-slate-900">2. Pilih Slot Kunjungan</h2>

                        <p v-if="loadingSlots" class="mt-4 text-sm text-slate-500">Memuat slot…</p>

                        <p v-else-if="slots.length === 0" class="mt-4 text-sm text-slate-500">
                            Tidak ada slot pada tanggal ini.
                        </p>

                        <div v-else class="mt-4 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="slot in slots"
                                :key="slot.id"
                                class="flex cursor-pointer items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                :class="[
                                    Number(form.visit_slot_id) === slot.id
                                        ? 'border-blue-600 bg-blue-50'
                                        : 'border-slate-200',
                                    slot.bookable ? '' : 'cursor-not-allowed opacity-50',
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <input
                                        v-model="form.visit_slot_id"
                                        type="radio"
                                        :value="slot.id"
                                        :disabled="!slot.bookable"
                                        class="text-blue-600"
                                    />
                                    <span class="font-medium text-slate-800">{{ slot.label }}</span>
                                </span>
                                <span class="text-xs text-slate-500">{{ slot.status_label }}</span>
                            </label>
                        </div>

                        <p v-if="form.errors.visit_slot_id" class="mt-3 text-sm text-rose-600">
                            {{ form.errors.visit_slot_id }}
                        </p>
                    </section>

                    <!-- Langkah 3: tiket -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-bold text-slate-900">3. Pilih Jenis dan Jumlah Tiket</h2>

                        <p v-if="ticketTypes.length === 0" class="mt-4 text-sm text-slate-500">
                            Museum ini belum menjual tiket.
                        </p>

                        <div v-else class="mt-4 space-y-3">
                            <div
                                v-for="type in ticketTypes"
                                :key="type.id"
                                class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-3 py-2"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ type.name }}</p>
                                    <p class="text-xs text-slate-500">{{ type.price_label }}</p>
                                </div>
                                <input
                                    :value="form.quantities[type.id] ?? 0"
                                    type="number"
                                    min="0"
                                    :max="selectedSlot?.remaining ?? 50"
                                    class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-center text-sm"
                                    @input="clampQuantity(type.id, ($event.target as HTMLInputElement).value)"
                                />
                            </div>
                        </div>

                        <p v-if="exceedsLimit" class="mt-3 text-sm text-rose-600">
                            Total melebihi batas {{ museum.max_tickets_per_booking }} tiket per pemesanan.
                        </p>
                        <p v-if="quantitiesError" class="mt-3 text-sm text-rose-600">
                            {{ quantitiesError }}
                        </p>
                    </section>

                    <!-- Langkah 4: data pemesan -->
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-bold text-slate-900">4. Data Pemesan</h2>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="contact_name" class="block text-sm font-medium text-slate-700">Nama</label>
                                <input
                                    id="contact_name"
                                    v-model="form.contact_name"
                                    type="text"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                                <p v-if="form.errors.contact_name" class="mt-1 text-sm text-rose-600">
                                    {{ form.errors.contact_name }}
                                </p>
                            </div>
                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-slate-700">Email</label>
                                <input
                                    id="contact_email"
                                    v-model="form.contact_email"
                                    type="email"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                                <p v-if="form.errors.contact_email" class="mt-1 text-sm text-rose-600">
                                    {{ form.errors.contact_email }}
                                </p>
                            </div>
                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-slate-700">Telepon</label>
                                <input
                                    id="contact_phone"
                                    v-model="form.contact_phone"
                                    type="tel"
                                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                                <p v-if="form.errors.contact_phone" class="mt-1 text-sm text-rose-600">
                                    {{ form.errors.contact_phone }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Ringkasan -->
                <aside class="lg:col-span-1">
                    <div class="sticky top-20 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-bold text-slate-900">Ringkasan</h2>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Tanggal</dt>
                                <dd class="font-medium text-slate-800">{{ selectedDate ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Slot</dt>
                                <dd class="font-medium text-slate-800">{{ selectedSlot?.label ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Jumlah tiket</dt>
                                <dd class="font-medium text-slate-800">{{ totalQuantity }}</dd>
                            </div>
                            <div class="mt-3 flex justify-between border-t border-slate-200 pt-3">
                                <dt class="font-semibold text-slate-700">Total</dt>
                                <dd class="text-lg font-bold text-slate-900">{{ totalAmountLabel }}</dd>
                            </div>
                        </dl>

                        <button
                            type="button"
                            class="mt-5 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            :disabled="!canSubmit || form.processing"
                            @click="submit"
                        >
                            {{ form.processing ? 'Memproses…' : 'Lanjut ke Pembayaran' }}
                        </button>

                        <p class="mt-3 text-xs text-slate-500">
                            Kuota ditahan selama 15 menit setelah pemesanan dibuat.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
