<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Meta tag SEO halaman publik (FR-PWA-01, NFR-16).
 *
 * SEO di MVP diselesaikan dengan meta server-rendered (judul, deskripsi,
 * canonical, Open Graph) + `sitemap.xml`; Inertia SSR opsional dan tidak
 * diaktifkan agar tidak menambah kompleksitas deployment.
 *
 * Setiap tag diberi `head-key`. Inertia memakai nilai itu sebagai
 * `data-inertia`, sehingga tag **menggantikan** baseline yang dirender
 * `app.blade.php` (bukan menambah duplikat) — crawler tanpa JS tetap
 * mendapatkan metadata, dan DOM hasil hidrasi hanya punya satu tag per jenis.
 */
interface Props {
    title: string;
    description?: string;
    image?: string | null;
    type?: 'website' | 'article';
    /** Path kanonik relatif, mis. `/museums/museum-batik`. */
    canonicalPath?: string;
    /** Jangan indeks (mis. halaman hasil filter). */
    noindex?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description:
        'Pesan tiket museum online, pilih slot kunjungan, bayar dengan QRIS atau e-wallet, dan masuk dengan e-ticket QR.',
    image: null,
    type: 'website',
    canonicalPath: undefined,
    noindex: false,
});

const canonical = computed<string>(() => {
    const origin = typeof window === 'undefined' ? '' : window.location.origin;
    const path = props.canonicalPath ?? (typeof window === 'undefined' ? '/' : window.location.pathname);

    return `${origin}${path}`;
});

const imageUrl = computed<string | null>(() => {
    if (props.image === null) {
        return null;
    }

    if (/^https?:\/\//.test(props.image)) {
        return props.image;
    }

    const origin = typeof window === 'undefined' ? '' : window.location.origin;

    return `${origin}${props.image}`;
});
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description" />
        <meta head-key="robots" name="robots" :content="noindex ? 'noindex, follow' : 'index, follow'" />
        <link head-key="canonical" rel="canonical" :href="canonical" />

        <meta head-key="og:type" property="og:type" :content="type" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta v-if="imageUrl" head-key="og:image" property="og:image" :content="imageUrl" />

        <meta head-key="twitter:card" name="twitter:card" :content="imageUrl ? 'summary_large_image' : 'summary'" />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <meta head-key="twitter:description" name="twitter:description" :content="description" />
        <meta v-if="imageUrl" head-key="twitter:image" name="twitter:image" :content="imageUrl" />
    </Head>
</template>
