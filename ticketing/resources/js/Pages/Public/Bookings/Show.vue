<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

interface BookingItemRow {
    id: number;
    name: string;
    quantity: number;
    unit_price_label: string;
    subtotal_label: string;
}

interface PaymentInfo {
    status: string;
    status_label: string;
    gross_amount: number;
    snap_token: string | null;
    snap_redirect_url: string | null;
    needs_review: boolean;
}

interface BookingDetail {
    uuid: string;
    booking_code: string;
    status: string;
    status_label: string;
    total_amount_label: string;
    museum_name: string;
    museum_slug: string | null;
    visit_date: string;
    slot_label: string;
    contact_name: string;
    contact_email: string;
    contact_phone: string;
    expires_at: string | null;
    seconds_until_expiry: number;
    is_payable: boolean;
    confirmed_at: string | null;
    items: BookingItemRow[];
    ticket_count: number;
    payment: PaymentInfo | null;
}

const props = defineProps<{
    booking: BookingDetail;
    snapClientKey: string | null;
    snapUrl: string | null;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

const status = ref({
    booking_status: props.booking.status,
    booking_status_label: props.booking.status_label,
    payment_status: props.booking.payment?.status ?? null,
    payment_status_label: props.booking.payment?.status_label ?? null,
    needs_review: props.booking.payment?.needs_review ?? false,
    ticket_count: props.booking.ticket_count,
    seconds_until_expiry: props.booking.seconds_until_expiry,
});

const remaining = ref<number>(props.booking.seconds_until_expiry);
let timer: number | undefined;
let poller: number | undefined;

const countdown = computed<string>(() => {
    if (remaining.value <= 0) {
        return '00:00';
    }

    const minutes = Math.floor(remaining.value / 60);
    const seconds = remaining.value % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const isPending = computed<boolean>(() => status.value.booking_status === 'pending_payment');

/**
 * Polling status dari backend (FR-PAY-05).
 *
 * Halaman ini **tidak pernah** menentukan status dari redirect gateway;
 * backend adalah satu-satunya sumber kebenaran (AC-8).
 */
const poll = async (): Promise<void> => {
    const response = await fetch(`/bookings/${props.booking.uuid}/status`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return;
    }

    status.value = await response.json();
    remaining.value = status.value.seconds_until_expiry;

    // Berhenti polling setelah status final.
    if (status.value.booking_status !== 'pending_payment' && poller !== undefined) {
        window.clearInterval(poller);
        poller = undefined;
    }
};

const cancel = (): void => {
    if (!confirm('Batalkan pemesanan ini? Kuota akan dilepas.')) {
        return;
    }

    router.post(`/bookings/${props.booking.uuid}/cancel`);
};

/**
 * Muat skrip Snap dari URL yang dikonfigurasi (`MIDTRANS_SNAP_URL`).
 *
 * Skrip tidak dimuat di `app.blade.php` karena hanya dibutuhkan di halaman
 * pembayaran, dan memuatnya di semua halaman memperlambat LCP.
 */
const loadSnapScript = (): Promise<void> => {
    if ((window as unknown as { snap?: unknown }).snap !== undefined) {
        return Promise.resolve();
    }

    const existing = document.querySelector<HTMLScriptElement>('script[data-snap-script]');

    if (existing !== null) {
        return new Promise<void>((resolve, reject) => {
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error('snap script failed')), { once: true });
        });
    }

    return new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = props.snapUrl ?? 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.async = true;
        script.dataset.snapScript = 'true';

        if (props.snapClientKey !== null && props.snapClientKey !== '') {
            script.dataset.clientKey = props.snapClientKey;
        }

        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener('error', () => reject(new Error('snap script failed')), { once: true });

        document.head.appendChild(script);
    });
};

const openSnap = async (): Promise<void> => {
    const token = props.booking.payment?.snap_token;

    if (token === null || token === undefined) {
        return;
    }

    try {
        await loadSnapScript();
    } catch {
        // Skrip tidak dapat dimuat (jaringan memblokir domain Midtrans):
        // arahkan ke halaman pembayaran Midtrans agar transaksi tetap selesai.
        redirectToSnapPage();

        return;
    }

    const snap = (window as unknown as { snap?: { pay: (token: string, options?: unknown) => void } }).snap;

    if (snap === undefined) {
        redirectToSnapPage();

        return;
    }

    snap.pay(token, {
        onSuccess: () => void poll(),
        onPending: () => void poll(),
        onError: () => void poll(),
        onClose: () => void poll(),
    });
};

const redirectToSnapPage = (): void => {
    const url = props.booking.payment?.snap_redirect_url;

    if (url === null || url === undefined || url === '') {
        return;
    }

    window.location.href = url;
};

const pay = (): void => {
    // Token Snap sudah ada (mis. menekan "Coba Bayar Lagi"): langsung buka
    // pembayaran. Bila belum, minta backend membuat transaksi lebih dulu.
    if (props.booking.payment?.snap_token) {
        void openSnap();

        return;
    }

    router.post(`/bookings/${props.booking.uuid}/pay`);
};

onMounted(() => {
    timer = window.setInterval(() => {
        if (remaining.value > 0) {
            remaining.value -= 1;
        }
    }, 1000);

    if (isPending.value) {
        poller = window.setInterval(() => void poll(), 5000);
    }
});

onBeforeUnmount(() => {
    if (timer !== undefined) {
        window.clearInterval(timer);
    }

    if (poller !== undefined) {
        window.clearInterval(poller);
    }
});
</script>

<template>
    <AppLayout>
        <Head :title="`Pemesanan ${booking.booking_code}`" />

        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kode Pemesanan</p>
                        <p class="mt-1 font-mono text-xl font-bold text-slate-900">{{ booking.booking_code }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ booking.museum_name }}</p>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-sm font-semibold"
                        :class="{
                            'bg-amber-100 text-amber-800': status.booking_status === 'pending_payment',
                            'bg-emerald-100 text-emerald-800': status.booking_status === 'confirmed',
                            'bg-slate-100 text-slate-700': status.booking_status === 'expired'
                                || status.booking_status === 'cancelled',
                            'bg-rose-100 text-rose-800': status.booking_status === 'refunded',
                        }"
                    >
                        {{ status.booking_status_label }}
                    </span>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">Tanggal kunjungan</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ booking.visit_date }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Slot</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ booking.slot_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Pemesan</dt>
                        <dd class="text-sm font-medium text-slate-800">{{ booking.contact_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Kontak</dt>
                        <dd class="text-sm font-medium text-slate-800">
                            {{ booking.contact_email }} · {{ booking.contact_phone }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-6 rounded-xl border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[36rem]">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-2">Jenis Tiket</th>
                                    <th class="px-4 py-2 text-center">Qty</th>
                                    <th class="px-4 py-2 text-right">Harga</th>
                                    <th class="px-4 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in booking.items" :key="item.id" class="border-t border-slate-100">
                                    <td class="px-4 py-2 text-slate-800">{{ item.name }}</td>
                                    <td class="px-4 py-2 text-center text-slate-700">{{ item.quantity }}</td>
                                    <td class="px-4 py-2 text-right text-slate-700">{{ item.unit_price_label }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-slate-900">{{ item.subtotal_label }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-slate-200 bg-slate-50">
                                    <td colspan="3" class="px-4 py-2 text-right font-semibold text-slate-700">Total</td>
                                    <td class="px-4 py-2 text-right text-base font-bold text-slate-900">
                                        {{ booking.total_amount_label }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Status pembayaran -->
                <div class="mt-6 rounded-xl bg-slate-50 p-4">
                    <p class="text-sm">
                        <span class="text-slate-500">Status pembayaran:</span>
                        <span class="font-semibold text-slate-800">
                            {{ status.payment_status_label ?? 'Belum dimulai' }}
                        </span>
                    </p>

                    <p v-if="isPending" class="mt-2 text-sm text-slate-600">
                        Sisa waktu pembayaran:
                        <span class="font-mono font-semibold text-amber-700">{{ countdown }}</span>
                    </p>

                    <p v-if="status.needs_review" class="mt-2 text-sm text-rose-700">
                        Pembayaran perlu ditinjau Admin. Tim kami akan menghubungi Anda.
                    </p>

                    <p v-if="status.booking_status === 'confirmed'" class="mt-2 text-sm text-emerald-700">
                        Pembayaran diterima. {{ status.ticket_count }} tiket telah diterbitkan.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="isPending"
                            type="button"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                            @click="pay"
                        >
                            {{ booking.payment === null ? 'Bayar Sekarang' : 'Coba Bayar Lagi' }}
                        </button>

                        <button
                            v-if="isPending"
                            type="button"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                            @click="cancel"
                        >
                            Batalkan Pemesanan
                        </button>
                    </div>
                </div>

                <p v-if="flash.error" class="mt-4 text-sm text-rose-600">{{ flash.error }}</p>
                <p v-if="flash.success" class="mt-4 text-sm text-emerald-700">{{ flash.success }}</p>

                <div class="mt-6 flex justify-between text-sm">
                    <Link href="/bookings" class="font-semibold text-blue-600 hover:underline">
                        Riwayat Pemesanan
                    </Link>
                    <Link
                        v-if="booking.museum_slug"
                        :href="`/museums/${booking.museum_slug}`"
                        class="font-semibold text-blue-600 hover:underline"
                    >
                        Lihat Museum
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
