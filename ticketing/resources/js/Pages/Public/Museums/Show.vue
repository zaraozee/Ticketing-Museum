<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

interface MuseumImage {
    url: string;
    caption: string | null;
}

interface FacilityItem {
    name: string;
    icon: string | null;
}

interface OperatingHour {
    day: string;
    open_time: string | null;
    close_time: string | null;
    is_closed: boolean;
}

const props = defineProps<{
    museum: {
        slug: string;
        name: string;
        description: string | null;
        category: string | null;
        address: string | null;
        city: string | null;
        province: string | null;
        latitude: string | null;
        longitude: string | null;
        booking_mode: string;
        max_tickets_per_booking: number;
        youtube_video_id: string | null;
        images: MuseumImage[];
        facilities: FacilityItem[];
        operating_hours: OperatingHour[];
    };
    directionsUrl: string | null;
    embedUrl: string | null;
}>();

const activeImage = ref(0);
const videoLoaded = ref(false);

const activeImageUrl = computed(() => props.museum.images[activeImage.value]?.url ?? null);

const locationLine = computed(() => {
    const parts = [props.museum.address, props.museum.city, props.museum.province].filter(Boolean);
    return parts.join(', ');
});
</script>

<template>
    <AppLayout>
        <Head :title="museum.name" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <Link href="/museums" class="text-sm font-medium text-blue-600 hover:underline">← Kembali ke daftar museum</Link>

            <div class="mt-4 grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Galeri -->
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                        <img
                            v-if="activeImageUrl"
                            :src="activeImageUrl"
                            :alt="museum.name"
                            class="aspect-[16/9] w-full object-cover"
                        />
                        <div v-else class="flex aspect-[16/9] items-center justify-center text-sm text-slate-400">
                            Belum ada foto museum
                        </div>
                    </div>

                    <div v-if="museum.images.length > 1" class="mt-3 flex gap-3 overflow-x-auto pb-1">
                        <button
                            v-for="(image, index) in museum.images"
                            :key="image.url"
                            type="button"
                            class="h-20 w-28 flex-shrink-0 overflow-hidden rounded-lg border-2 transition"
                            :class="index === activeImage ? 'border-blue-600' : 'border-transparent hover:border-slate-300'"
                            @click="activeImage = index"
                        >
                            <img :src="image.url" :alt="image.caption || museum.name" class="h-full w-full object-cover" />
                        </button>
                    </div>

                    <div class="mt-8">
                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ museum.name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                            <span v-if="museum.category" class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700">
                                {{ museum.category }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-600">
                                {{ museum.booking_mode }}
                            </span>
                        </div>
                        <p v-if="locationLine" class="mt-3 text-slate-600">{{ locationLine }}</p>

                        <div class="prose prose-slate mt-6 max-w-none text-slate-700">
                            <p v-if="museum.description" class="whitespace-pre-line leading-relaxed">{{ museum.description }}</p>
                            <p v-else class="text-slate-500">Deskripsi museum belum tersedia.</p>
                        </div>
                    </div>

                    <!-- Fasilitas -->
                    <section v-if="museum.facilities.length" class="mt-10">
                        <h2 class="text-lg font-bold text-slate-900">Fasilitas</h2>
                        <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <li
                                v-for="facility in museum.facilities"
                                :key="facility.name"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                {{ facility.name }}
                            </li>
                        </ul>
                    </section>

                    <!-- Jam operasional -->
                    <section v-if="museum.operating_hours.length" class="mt-10">
                        <h2 class="text-lg font-bold text-slate-900">Jam Operasional</h2>
                        <table class="mt-3 w-full overflow-hidden rounded-xl border border-slate-200 text-sm">
                            <tbody>
                                <tr v-for="hour in museum.operating_hours" :key="hour.day" class="border-b border-slate-100 last:border-0">
                                    <th class="w-32 bg-slate-50 px-4 py-2 text-left font-medium text-slate-600">{{ hour.day }}</th>
                                    <td class="px-4 py-2 text-slate-700">
                                        <span v-if="hour.is_closed" class="font-medium text-rose-600">Tutup</span>
                                        <span v-else>{{ hour.open_time }} – {{ hour.close_time }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <!-- Video YouTube (facade, THIRD_PARTY §6) -->
                    <section v-if="museum.youtube_video_id" class="mt-10">
                        <h2 class="text-lg font-bold text-slate-900">Video Profil</h2>
                        <div class="mt-3 aspect-video overflow-hidden rounded-2xl border border-slate-200 bg-slate-900">
                            <iframe
                                v-if="videoLoaded"
                                :src="`https://www.youtube-nocookie.com/embed/${museum.youtube_video_id}?autoplay=1`"
                                title="Video profil museum"
                                class="h-full w-full"
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            />
                            <button
                                v-else
                                type="button"
                                class="group relative h-full w-full"
                                @click="videoLoaded = true"
                            >
                                <img
                                    :src="`https://i.ytimg.com/vi/${museum.youtube_video_id}/hqdefault.jpg`"
                                    alt="Pratinjau video museum"
                                    class="h-full w-full object-cover opacity-80 transition group-hover:opacity-100"
                                    loading="lazy"
                                />
                                <span class="absolute inset-0 flex items-center justify-center">
                                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white/90 text-2xl text-slate-900 shadow-lg">▶</span>
                                </span>
                            </button>
                        </div>
                    </section>
                </div>

                <!-- Panel samping -->
                <aside class="lg:col-span-1">
                    <div class="sticky top-24 space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-base font-bold text-slate-900">Rencanakan Kunjungan</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Maksimal {{ museum.max_tickets_per_booking }} tiket per pemesanan.
                            </p>
                            <p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                Pemesanan tiket dibuka pada fase berikutnya. Saat ini kamu dapat melihat informasi museum.
                            </p>
                            <button
                                type="button"
                                disabled
                                class="mt-4 w-full cursor-not-allowed rounded-xl bg-slate-200 px-4 py-3 text-sm font-semibold text-slate-500"
                            >
                                Pesan Tiket (segera hadir)
                            </button>
                        </div>

                        <div v-if="directionsUrl || embedUrl" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 class="text-base font-bold text-slate-900">Lokasi</h2>
                            <div v-if="embedUrl" class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                                <iframe
                                    :src="embedUrl"
                                    title="Peta lokasi museum"
                                    class="aspect-[4/3] w-full"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    allowfullscreen
                                />
                            </div>
                            <a
                                v-if="directionsUrl"
                                :href="directionsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                            >
                                Petunjuk Arah
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
