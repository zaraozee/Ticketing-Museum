<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import SeoHead from '../Components/SeoHead.vue';
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Pemesanan Tiket Museum Online"
            description="Pesan tiket museum secara instan, pilih slot kunjungan favoritmu, bayar dengan QRIS atau e-wallet, dan masuk dengan e-ticket QR terverifikasi."
            canonical-path="/"
        />

        <!-- Hero Section -->
        <section class="relative border-b border-slate-200 bg-gradient-to-b from-blue-50/60 to-white py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <span class="mb-6 inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                    Platform Tiket Museum PWA
                </span>
                <h1 class="mx-auto max-w-3xl text-3xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-5xl">
                    Jelajahi Sejarah &amp; Budaya Nusantara Tanpa Antre
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-slate-600 sm:text-lg">
                    Pesan tiket museum secara instan, pilih slot kunjungan favoritmu, dan masuk dengan mudah menggunakan E-Ticket QR terverifikasi.
                </p>

                <!-- Action buttons -->
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link
                        href="/register"
                        class="rounded-xl bg-blue-600 px-6 py-3 font-semibold text-white shadow-md shadow-blue-500/20 transition hover:bg-blue-700"
                    >
                        Mulai Pesan Tiket
                    </Link>
                    <Link
                        href="/register/organizer"
                        class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Gabung Sebagai Pengelola
                    </Link>
                </div>
            </div>
        </section>

        <!-- Feature Highlight Section -->
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="mb-12 text-center">
                <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Kenapa Memilih Jejak?</h2>
                <p class="mt-2 text-slate-600">Teknologi modern untuk kenyamanan pengunjung dan efisiensi pengelola museum.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-xl font-bold text-blue-600">
                        1
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-900">Pilih Slot &amp; Kuota Realtime</h3>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Kapasitas slot terkontrol dengan akurasi anti-overbooking untuk kenyamanan kunjungan museum yang terjaga.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-xl font-bold text-indigo-600">
                        2
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-900">E-Ticket QR Terverifikasi</h3>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Tiket digital berstandar kriptografi HMAC aman, dapat dibuka offline saat di lokasi dan dipindai sekejap.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-xl font-bold text-emerald-600">
                        3
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-900">Pembayaran Instan &amp; Otomatis</h3>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Dukungan pembayaran QRIS, e-wallet, dan transfer bank terverifikasi otomatis dengan notifikasi real-time.
                    </p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
