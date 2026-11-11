<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps<{
    page: {
        title: string;
        slug: string;
        content: string | null;
        type: string;
    };
}>();
</script>

<template>
    <AppLayout>
        <Head :title="page.title" />

        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <Link href="/bantuan" class="text-sm font-medium text-blue-600 hover:underline">← Kembali ke Bantuan</Link>

            <span class="mt-4 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ page.type }}
            </span>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">{{ page.title }}</h1>

            <div class="mt-6 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                {{ page.content || 'Konten halaman ini belum tersedia.' }}
            </div>
        </div>
    </AppLayout>
</template>
