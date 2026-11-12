<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import {
    getOfflineTicket,
    offlineStatusLabel,
    saveOfflineTicket,
    type OfflineTicket,
} from '@/composables/offlineTickets';

interface TicketDetail {
    uuid: string;
    ticket_code: string;
    status: string;
    status_label: string;
    museum_name: string;
    museum_address: string;
    museum_city: string;
    visit_date: string;
    slot_label: string;
    ticket_type_name: string;
    booking_code: string;
    contact_name: string;
    contact_email: string;
    used_at: string | null;
}

const props = defineProps<{
    ticket: TicketDetail;
}>();

const { online } = useOnlineStatus();

const qrDataUrl = ref<string>('');
const qrError = ref<string>('');
const cachedAt = ref<string | null>(null);
const loading = ref<boolean>(false);

/**
 * Saat offline, tiket ditampilkan dari IndexedDB dengan label
 * "Tidak terverifikasi" (SYSTEM_DESIGN §15.3, AC-3 Fase 8).
 */
const offlineLabel = computed<string | null>(() => {
    if (online.value || cachedAt.value === null) {
        return null;
    }

    return offlineStatusLabel(cachedAt.value);
});

const badgeClass = computed(() => {
    const map: Record<string, string> = {
        active: 'bg-emerald-100 text-emerald-800',
        used: 'bg-slate-100 text-slate-700',
        void: 'bg-rose-100 text-rose-800',
        expired: 'bg-amber-100 text-amber-800',
    };

    return map[props.ticket.status] || 'bg-slate-100 text-slate-700';
});

/**
 * Render QR di sisi klien dari payload token, sehingga tetap tersedia offline.
 * Library `qrcode` dimuat dinamis agar tidak membebani bundel halaman lain.
 */
async function renderQr(token: string): Promise<string> {
    const { default: QRCode } = await import('qrcode');

    return QRCode.toDataURL(token, {
        width: 320,
        margin: 2,
        errorCorrectionLevel: 'M',
        color: { dark: '#0f172a', light: '#ffffff' },
    });
}

async function loadFromCache(): Promise<boolean> {
    const cached = await getOfflineTicket(props.ticket.uuid);

    if (!cached) {
        return false;
    }

    qrDataUrl.value = cached.qr_data_url;
    cachedAt.value = cached.cached_at;

    return true;
}

async function load(): Promise<void> {
    loading.value = true;
    qrError.value = '';

    try {
        // Coba server dulu; bila jaringan gagal, pakai salinan offline.
        const response = await fetch(`/api/v1/tickets/${props.ticket.uuid}/token`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Gagal memuat token QR.');
        }

        const { qr_token: token } = (await response.json()) as { qr_token: string };

        qrDataUrl.value = await renderQr(token);
        cachedAt.value = new Date().toISOString();

        const snapshot: OfflineTicket = {
            uuid: props.ticket.uuid,
            ticket_code: props.ticket.ticket_code,
            qr_token: token,
            qr_data_url: qrDataUrl.value,
            status: props.ticket.status,
            status_label: props.ticket.status_label,
            museum_name: props.ticket.museum_name,
            museum_address: props.ticket.museum_address,
            museum_city: props.ticket.museum_city,
            visit_date: props.ticket.visit_date,
            slot_label: props.ticket.slot_label,
            ticket_type_name: props.ticket.ticket_type_name,
            booking_code: props.ticket.booking_code,
            contact_name: props.ticket.contact_name,
            cached_at: cachedAt.value,
        };

        await saveOfflineTicket(snapshot);
    } catch {
        const restored = await loadFromCache();

        if (!restored) {
            qrError.value = 'QR belum tersimpan di perangkat. Buka tiket ini saat online terlebih dahulu.';
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (props.ticket.status === 'active') {
        void load();
    }
});

function addToCalendar(): void {
    const start = props.ticket.visit_date.replace(/-/g, '');
    const title = encodeURIComponent(`Kunjungan ${props.ticket.museum_name}`);
    const location = encodeURIComponent(`${props.ticket.museum_address}, ${props.ticket.museum_city}`);
    const url = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${start}/${start}&location=${location}`;

    window.open(url, '_blank', 'noopener');
}
</script>

<template>
    <AppLayout>
        <Head :title="`E-Ticket ${ticket.ticket_code}`" />

        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
            <div class="mb-6">
                <Link href="/tickets" class="text-sm font-semibold text-blue-600 hover:underline">
                    ← Kembali ke Tiket Saya
                </Link>
            </div>

            <!-- Banner offline (SYSTEM_DESIGN §15.3) -->
            <div
                v-if="offlineLabel"
                class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                role="status"
            >
                <span aria-hidden="true" class="text-lg leading-none">⚠</span>
                <div>
                    <p class="font-semibold">{{ offlineLabel }}</p>
                    <p class="mt-0.5 text-xs text-amber-800">
                        Validitas tiket ditentukan oleh petugas saat pemindaian di gerbang.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <!-- Header -->
                <div class="border-b border-slate-100 pb-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold text-slate-900">{{ ticket.museum_name }}</h1>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ ticket.visit_date }} · {{ ticket.slot_label }}
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1.5 text-xs font-semibold" :class="badgeClass">
                            {{ ticket.status_label }}
                        </span>
                    </div>
                </div>

                <!-- QR Code -->
                <div v-if="ticket.status === 'active'" class="my-6 flex justify-center">
                    <div class="rounded-xl border-2 border-slate-200 bg-white p-3 sm:p-4">
                        <img
                            v-if="qrDataUrl"
                            :src="qrDataUrl"
                            alt="Kode QR tiket"
                            class="h-56 w-56 sm:h-64 sm:w-64"
                            width="320"
                            height="320"
                        />
                        <div
                            v-else-if="qrError"
                            class="flex h-56 w-56 items-center justify-center px-4 text-center text-sm text-red-600 sm:h-64 sm:w-64"
                        >
                            {{ qrError }}
                        </div>
                        <div
                            v-else
                            class="flex h-56 w-56 items-center justify-center text-sm text-slate-500 sm:h-64 sm:w-64"
                            aria-live="polite"
                        >
                            {{ loading ? 'Memuat QR…' : 'QR belum tersedia' }}
                        </div>
                    </div>
                </div>

                <!-- Ticket Details -->
                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="font-medium text-slate-600">Kode Tiket</dt>
                        <dd class="font-mono font-semibold text-slate-900">{{ ticket.ticket_code }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-medium text-slate-600">Jenis Tiket</dt>
                        <dd class="text-right font-medium text-slate-900">{{ ticket.ticket_type_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-medium text-slate-600">Booking</dt>
                        <dd class="font-mono font-medium text-slate-900">{{ ticket.booking_code }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-medium text-slate-600">Pemesan</dt>
                        <dd class="text-right font-medium text-slate-900">{{ ticket.contact_name }}</dd>
                    </div>
                    <div v-if="ticket.used_at" class="flex justify-between gap-4">
                        <dt class="font-medium text-slate-600">Digunakan</dt>
                        <dd class="font-medium text-slate-900">{{ ticket.used_at }}</dd>
                    </div>
                </dl>

                <!-- Actions -->
                <div v-if="ticket.status === 'active'" class="mt-6 space-y-2">
                    <button
                        type="button"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                        @click="addToCalendar"
                    >
                        Tambah ke Kalender
                    </button>
                    <p class="text-center text-xs text-slate-500">
                        Tunjukkan QR ini di gerbang museum. Kecerahan layar maksimal akan membantu pemindaian.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
