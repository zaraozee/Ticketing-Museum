<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <Head title="Pemesanan Tiket Museum Online" />

        <!-- Hero Section -->
        <section class="relative bg-gradient-to-b from-blue-50/60 to-white py-16 sm:py-24 border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 mb-6">
                    Platform Tiket Museum PWA
                </span>
                <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight max-w-3xl mx-auto leading-tight">
                    Jelajahi Sejarah & Budaya Nusantara Tanpa Antre
                </h1>
                <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                    Pesan tiket museum secara instan, pilih slot kunjungan favoritmu, dan masuk dengan mudah menggunakan E-Ticket QR terverifikasi.
                </p>

                <!-- Action buttons -->
                <div class="mt-8 flex flex-col sm:flex-row justify-center gap-3">
                    <Link
                        href="/register"
                        class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-md shadow-blue-500/20 transition"
                    >
                        Mulai Pesan Tiket
                    </Link>
                    <Link
                        href="/register/organizer"
                        class="px-6 py-3 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold transition"
                    >
                        Gabung Sebagai Pengelola
                    </Link>
                </div>
            </div>
        </section>

        <!-- Feature Highlight Section -->
        <section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Kenapa Memilih Jejak?</h2>
                <p class="mt-2 text-slate-600">Teknologi modern untuk kenyamanan pengunjung dan efisiensi pengelola museum.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 bg-white border border-slate-200 rounded-2xl shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xl mb-4">
                        1
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Pilih Slot & Kuota Realtime</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Kapasitas slot terkontrol dengan akurasi anti-overbooking untuk kenyamanan kunjungan museum yang terjaga.
                    </p>
                </div>

                <div class="p-6 bg-white border border-slate-200 rounded-2xl shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xl mb-4">
                        2
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">E-Ticket QR Terverifikasi</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Tiket digital berstandar kriptografi HMAC aman, dapat dibuka offline saat di lokasi dan dipindai sekejap.
                    </p>
                </div>

                <div class="p-6 bg-white border border-slate-200 rounded-2xl shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xl mb-4">
                        3
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Pembayaran Instan & Otomatis</h3>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Dukungan pembayaran QRIS, e-wallet, dan transfer bank terverifikasi otomatis dengan notifikasi real-time.
                    </p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
