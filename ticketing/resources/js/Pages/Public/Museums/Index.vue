<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

interface MuseumCard {
    slug: string;
    name: string;
    city: string | null;
    province: string | null;
    category: string | null;
    excerpt: string;
    cover_url: string | null;
    booking_mode: string;
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    museums: {
        data: MuseumCard[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q: string; category: string; city: string };
    categories: Option[];
    cities: string[];
}>();

const search = ref(props.filters.q);
const category = ref(props.filters.category);
const city = ref(props.filters.city);

const applyFilters = () => {
    router.get(
        '/museums',
        { q: search.value || undefined, category: category.value || undefined, city: city.value || undefined },
        { preserveState: true, replace: true },
    );
};

watch([category, city], applyFilters);

const resetFilters = () => {
    search.value = '';
    category.value = '';
    city.value = '';
    router.get('/museums', {}, { preserveState: true, replace: true });
};
</script>

<template>
    <AppLayout>
        <Head title="Jelajah Museum" />

        <section class="bg-gradient-to-b from-blue-50/70 to-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Jelajah Museum</h1>
                <p class="mt-2 text-slate-600">Temukan museum, galeri, dan situs budaya yang bisa kamu kunjungi hari ini.</p>

                <form class="mt-6 flex flex-col sm:flex-row gap-3" @submit.prevent="applyFilters">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama museum atau kota..."
                        class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none"
                    />
                    <button
                        type="submit"
                        class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    >
                        Cari
                    </button>
                </form>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="category === '' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'"
                        @click="category = ''"
                    >
                        Semua Kategori
                    </button>
                    <button
                        v-for="option in categories"
                        :key="option.value"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="category === option.value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50'"
                        @click="category = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <label for="city-filter" class="text-xs font-medium text-slate-500">Kota:</label>
                    <select
                        id="city-filter"
                        v-model="city"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-blue-500 focus:outline-none"
                    >
                        <option value="">Semua kota</option>
                        <option v-for="item in cities" :key="item" :value="item">{{ item }}</option>
                    </select>
                    <button
                        v-if="search || category || city"
                        type="button"
                        class="text-xs font-semibold text-blue-600 hover:underline"
                        @click="resetFilters"
                    >
                        Reset filter
                    </button>
                </div>
            </div>
        </section>

        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <p class="mb-6 text-sm text-slate-500">
                <template v-if="museums.total > 0">
                    Menampilkan {{ museums.from }}–{{ museums.to }} dari {{ museums.total }} museum
                </template>
                <template v-else> Tidak ada museum yang cocok dengan pencarianmu. </template>
            </p>

            <div v-if="museums.data.length" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="museum in museums.data"
                    :key="museum.slug"
                    :href="`/museums/${museum.slug}`"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="aspect-[16/10] w-full overflow-hidden bg-slate-100">
                        <img
                            v-if="museum.cover_url"
                            :src="museum.cover_url"
                            :alt="museum.name"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex h-full items-center justify-center text-sm text-slate-400">
                            Belum ada foto
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-5">
                        <div class="flex items-center gap-2 text-xs">
                            <span v-if="museum.category" class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700">
                                {{ museum.category }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">
                                {{ museum.booking_mode }}
                            </span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900 group-hover:text-blue-700">{{ museum.name }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ museum.city || 'Lokasi belum diisi' }}<span v-if="museum.province">, {{ museum.province }}</span>
                        </p>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ museum.excerpt }}</p>
                        <span class="mt-auto pt-3 text-sm font-semibold text-blue-600">Lihat detail →</span>
                    </div>
                </Link>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                <p class="text-slate-500">Belum ada museum yang tayang saat ini.</p>
            </div>

            <nav v-if="museums.links.length > 3" class="mt-10 flex flex-wrap items-center justify-center gap-1">
                <template v-for="(link, index) in museums.links" :key="index">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-lg px-3 py-2 text-sm text-slate-400"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </section>
    </AppLayout>
</template>
