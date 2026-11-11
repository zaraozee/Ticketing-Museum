<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    organization_name: '',
    contact_phone: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register/organizer', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Pendaftaran Pengelola Museum" />

        <template #subtitle>
            Daftarkan museum atau galeri Anda untuk membuka pemesanan tiket online
        </template>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800 leading-relaxed mb-4">
                <strong>Catatan:</strong> Akun pengelola museum akan ditinjau oleh Admin Platform sebelum dapat mempublikasikan museum dan membuka penjualan tiket.
            </div>

            <div>
                <label for="organization_name" class="block text-sm font-medium text-slate-700">Nama Organisasi / Yayasan / Badan Pengelola</label>
                <input
                    id="organization_name"
                    type="text"
                    v-model="form.organization_name"
                    required
                    autofocus
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="Contoh: Yayasan Museum Tekstil Nusantara"
                />
                <div v-if="form.errors.organization_name" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.organization_name }}
                </div>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nama Penanggung Jawab</label>
                <input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="Nama lengkap PIC"
                />
                <div v-if="form.errors.name" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.name }}
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Email Resmi Organisasi</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="kontak@museumanda.com"
                />
                <div v-if="form.errors.email" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.email }}
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700">No. HP / WA PIC</label>
                    <input
                        id="phone"
                        type="tel"
                        v-model="form.phone"
                        required
                        class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        placeholder="08xxxxxxxxxx"
                    />
                    <div v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.phone }}
                    </div>
                </div>

                <div>
                    <label for="contact_phone" class="block text-sm font-medium text-slate-700">Telp Kantor (Opsional)</label>
                    <input
                        id="contact_phone"
                        type="tel"
                        v-model="form.contact_phone"
                        class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        placeholder="021-xxxxxxx"
                    />
                    <div v-if="form.errors.contact_phone" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.contact_phone }}
                    </div>
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="Minimal 8 karakter"
                />
                <div v-if="form.errors.password" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.password }}
                </div>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Konfirmasi Kata Sandi</label>
                <input
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="Ulangi kata sandi"
                />
                <div v-if="form.errors.password_confirmation" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.password_confirmation }}
                </div>
            </div>

            <div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
                >
                    {{ form.processing ? 'Mengirim Pendaftaran...' : 'Ajukan Pendaftaran Pengelola' }}
                </button>
            </div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-600">
                Sudah memiliki akun pengelola?
                <Link href="/login" class="font-semibold text-blue-600 hover:text-blue-500 ml-1">
                    Masuk di sini
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
