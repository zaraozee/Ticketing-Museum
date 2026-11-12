<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import OrganizerLayout from '@/Layouts/OrganizerLayout.vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';

interface Museum {
    id: number;
    name: string;
    slug: string;
}

interface ScanTicket {
    uuid: string;
    code: string;
    type: string;
    holder: string;
    visit_date: string;
    slot_time: string;
}

interface ScanResult {
    result: string;
    label: string;
    color: string;
    allows_entry: boolean;
    message: string;
    ticket: ScanTicket | null;
}

const props = defineProps<{
    museums: Museum[];
}>();

const { online } = useOnlineStatus();

const selectedMuseum = ref<number | null>(props.museums[0]?.id ?? null);
const scanResult = ref<ScanResult | null>(null);
const scanning = ref(false);
const manualCode = ref('');
const videoRef = ref<HTMLVideoElement | null>(null);
const stream = ref<MediaStream | null>(null);
const errorMessage = ref<string>('');
const cameraActive = ref(false);

/**
 * Pemindaian butuh server untuk memvalidasi tiket (FR-GATE-01), jadi kamera
 * otomatis dihentikan saat perangkat offline. Tiket offline tetap dapat
 * ditunjukkan ke petugas, tetapi verifikasi dilakukan saat jaringan kembali.
 */
const canScan = computed(() => selectedMuseum.value !== null && online.value && !scanning.value);

const resultColors: Record<string, string> = {
    green: 'bg-emerald-500',
    red: 'bg-rose-500',
    yellow: 'bg-amber-500',
    gray: 'bg-slate-500',
    orange: 'bg-orange-500',
};

const resultTextColors: Record<string, string> = {
    green: 'text-emerald-700',
    red: 'text-rose-700',
    yellow: 'text-amber-700',
    gray: 'text-slate-700',
    orange: 'text-orange-700',
};

/** Interval minimum antar-pemindaian otomatis (ms) agar tidak spam API. */
const SCAN_COOLDOWN_MS = 2000;

let lastScanAt = 0;
let frameHandle: number | null = null;
let loopActive = false;

function generateUuid(): string {
    return crypto.randomUUID();
}

async function startCamera(): Promise<void> {
    if (!online.value) {
        errorMessage.value = 'Kamera tidak dapat dipakai saat offline. Gunakan input manual setelah jaringan kembali.';
        return;
    }

    errorMessage.value = '';

    try {
        const mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });

        stream.value = mediaStream;
        cameraActive.value = true;

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play().catch(() => undefined);
            loopActive = true;
            scanLoop();
        }
    } catch {
        errorMessage.value = 'Tidak dapat mengakses kamera. Gunakan input manual.';
        stopCamera();
    }
}

function stopCamera(): void {
    loopActive = false;

    if (frameHandle !== null) {
        cancelAnimationFrame(frameHandle);
        frameHandle = null;
    }

    if (stream.value) {
        stream.value.getTracks().forEach((track) => track.stop());
        stream.value = null;
    }

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    cameraActive.value = false;
}

/**
 * Loop pemindaian. `jsQR` diimpor dinamis agar bundel awal tetap ringan
 * (target LCP ≤ 2,5 s pada 4G, NFR-04).
 */
async function scanLoop(): Promise<void> {
    if (!loopActive) {
        return;
    }

    const video = videoRef.value;

    if (!video || !stream.value) {
        loopActive = false;

        return;
    }

    if (video.readyState === video.HAVE_ENOUGH_DATA && !scanning.value) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        if (ctx) {
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

            const { default: jsQR } = await import('jsqr');
            const code = jsQR(imageData.data, imageData.width, imageData.height);

            if (code?.data && Date.now() - lastScanAt > SCAN_COOLDOWN_MS) {
                lastScanAt = Date.now();
                await submitScan(code.data, null);
            }
        }
    }

    if (loopActive) {
        frameHandle = requestAnimationFrame(() => {
            void scanLoop();
        });
    }
}

async function submitScan(qrPayload: string | null, ticketCode: string | null): Promise<void> {
    if (!selectedMuseum.value || scanning.value) {
        return;
    }

    if (!online.value) {
        errorMessage.value = 'Verifikasi tiket membutuhkan koneksi internet. Coba lagi saat online.';

        return;
    }

    scanning.value = true;
    scanResult.value = null;
    errorMessage.value = '';

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const response = await fetch('/api/v1/gate/scans', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token || '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                museum_id: selectedMuseum.value,
                qr_payload: qrPayload,
                ticket_code: ticketCode,
                // Idempotensi: UUID dibuat per percobaan scan (FR-GATE-05).
                client_scan_uuid: generateUuid(),
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            errorMessage.value =
                data.error || data.message || data.errors?.qr_payload?.[0] || 'Gagal memindai tiket.';

            return;
        }

        scanResult.value = data as ScanResult;
    } catch {
        errorMessage.value = 'Kesalahan jaringan. Periksa koneksi lalu coba lagi.';
    } finally {
        scanning.value = false;
    }
}

function submitManual(): void {
    const code = manualCode.value.trim().toUpperCase();

    if (code.length === 0) {
        return;
    }

    void submitScan(null, code);
    manualCode.value = '';
}

onMounted(() => {
    if (props.museums.length === 0) {
        errorMessage.value = 'Anda belum ditugaskan ke museum mana pun.';
    }
});

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <OrganizerLayout>
        <Head title="Gate Scanner" />

        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Gate Scanner</h1>
                    <p class="mt-1 text-sm text-slate-600">Pindai QR tiket pengunjung atau masukkan kode manual.</p>
                </div>
                <Link
                    v-if="selectedMuseum"
                    :href="`/organizer/scans?museum_id=${selectedMuseum}`"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Riwayat Scan
                </Link>
            </div>

            <!-- Status offline (FR-PWA-03) -->
            <div v-if="!online" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900" role="status">
                Perangkat offline. Pemindaian dinonaktifkan sampai koneksi kembali — validasi tiket wajib lewat server.
            </div>

            <!-- Museum Selector -->
            <div class="mb-4">
                <label for="museum" class="mb-1 block text-sm font-medium text-slate-700">Museum</label>
                <select
                    id="museum"
                    v-model="selectedMuseum"
                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base focus:border-blue-500 focus:outline-none"
                >
                    <option v-for="museum in museums" :key="museum.id" :value="museum.id">
                        {{ museum.name }}
                    </option>
                </select>
            </div>

            <!-- Camera -->
            <div v-if="selectedMuseum" class="mb-4 overflow-hidden rounded-2xl border border-slate-200 bg-black">
                <video ref="videoRef" class="h-auto w-full" autoplay muted playsinline aria-label="Pratinjau kamera"></video>
                <div class="flex flex-col gap-3 bg-slate-900 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        v-if="!cameraActive"
                        type="button"
                        :disabled="!online"
                        class="min-h-[44px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="startCamera"
                    >
                        Aktifkan Kamera
                    </button>
                    <button
                        v-else
                        type="button"
                        class="min-h-[44px] rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                        @click="stopCamera"
                    >
                        Stop Kamera
                    </button>
                    <p class="text-xs text-slate-400">
                        {{ scanning ? 'Memverifikasi…' : 'Arahkan QR ke kamera' }}
                    </p>
                </div>
            </div>

            <!-- Manual Input -->
            <div v-if="selectedMuseum" class="mb-6">
                <label for="manual" class="mb-1 block text-sm font-medium text-slate-700">
                    Atau masukkan kode tiket manual
                </label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        id="manual"
                        v-model="manualCode"
                        type="text"
                        maxlength="10"
                        inputmode="text"
                        autocapitalize="characters"
                        autocomplete="off"
                        placeholder="Kode 10 karakter"
                        class="min-h-[44px] flex-1 rounded-xl border border-slate-300 px-4 py-3 text-base uppercase focus:border-blue-500 focus:outline-none"
                        @keyup.enter="submitManual"
                    />
                    <button
                        type="button"
                        :disabled="!canScan || !manualCode.trim()"
                        class="min-h-[44px] rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="submitManual"
                    >
                        Periksa
                    </button>
                </div>
            </div>

            <!-- Error -->
            <div v-if="errorMessage" class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-700" role="alert">
                {{ errorMessage }}
            </div>

            <!-- Scan Result -->
            <div v-if="scanResult" class="rounded-2xl border-2 border-slate-200 p-6 text-center" role="status" aria-live="polite">
                <div
                    :class="resultColors[scanResult.color] || 'bg-slate-500'"
                    class="mx-auto mb-3 inline-flex rounded-full px-6 py-3"
                >
                    <span class="text-lg font-bold text-white">{{ scanResult.label }}</span>
                </div>
                <div v-if="scanResult.ticket" class="mt-4 text-left">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-600">Kode</dt>
                            <dd class="font-mono font-semibold text-slate-900">{{ scanResult.ticket.code }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-600">Jenis</dt>
                            <dd class="text-right font-medium text-slate-900">{{ scanResult.ticket.type }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-600">Pemesan</dt>
                            <dd class="text-right font-medium text-slate-900">{{ scanResult.ticket.holder }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-600">Kunjungan</dt>
                            <dd class="text-right font-medium text-slate-900">
                                {{ scanResult.ticket.visit_date }} · {{ scanResult.ticket.slot_time }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <p class="mt-4 text-sm" :class="resultTextColors[scanResult.color] || 'text-slate-600'">
                    {{ scanResult.message }}
                </p>
            </div>
        </div>
    </OrganizerLayout>
</template>
