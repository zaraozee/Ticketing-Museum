<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';

const props = defineProps<{
    page: {
        title: string;
        slug: string;
        content: string | null;
        type: string;
    };
}>();

const seoDescription = computed<string>(() => {
    const source = props.page.content?.replace(/\s+/g, ' ').trim();

    if (!source) {
        return `${props.page.title} — informasi resmi Jejak, platform pemesanan tiket museum online.`;
    }

    return source.length > 155 ? `${source.slice(0, 152).trimEnd()}…` : source;
});
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="page.title"
            :description="seoDescription"
            :canonical-path="`/bantuan/${page.slug}`"
        />

        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <Link href="/bantuan" class="text-sm font-medium text-blue-600 hover:underline">â† Kembali ke Bantuan</Link>

            <span class="mt-4 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ page.type }}
            </span>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ page.title }}</h1>

            <div class="mt-6 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                {{ page.content || 'Konten halaman ini belum tersedia.' }}
            </div>
        </div>
    </AppLayout>
</template>
