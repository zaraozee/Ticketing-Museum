<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

interface FaqItem {
    title: string;
    slug: string;
    content: string | null;
}

interface PageLink {
    title: string;
    slug: string;
}

defineProps<{ faqs: FaqItem[]; pages: PageLink[] }>();

const openSlug = ref<string | null>(null);

const toggle = (slug: string) => {
    openSlug.value = openSlug.value === slug ? null : slug;
};
</script>

<template>
    <AppLayout>
        <Head title="Bantuan" />

        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Bantuan</h1>
            <p class="mt-2 text-slate-600">Pertanyaan umum seputar pemesanan tiket dan kunjungan museum.</p>

            <section class="mt-8">
                <h2 class="text-lg font-bold text-slate-900">Pertanyaan yang Sering Diajukan</h2>

                <div v-if="faqs.length" class="mt-3 divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div v-for="faq in faqs" :key="faq.slug">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50"
                            @click="toggle(faq.slug)"
                        >
                            <span class="text-sm font-semibold text-slate-900">{{ faq.title }}</span>
                            <span class="text-slate-400">{{ openSlug === faq.slug ? '−' : '+' }}</span>
                        </button>
                        <div v-show="openSlug === faq.slug" class="px-5 pb-4 text-sm leading-relaxed whitespace-pre-line text-slate-700">
                            {{ faq.content || 'Jawaban belum tersedia.' }}
                        </div>
                    </div>
                </div>

                <p v-else class="mt-3 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                    Belum ada FAQ yang tayang.
                </p>
            </section>

            <section v-if="pages.length" class="mt-10">
                <h2 class="text-lg font-bold text-slate-900">Kebijakan &amp; Ketentuan</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="page in pages" :key="page.slug">
                        <Link
                            :href="`/bantuan/${page.slug}`"
                            class="block rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-medium text-blue-700 transition hover:bg-blue-50"
                        >
                            {{ page.title }}
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
