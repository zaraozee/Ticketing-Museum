<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Daftar Akun Pengunjung" />

        <template #subtitle>
            Daftar akun untuk kemudahan pemesanan dan penyimpanan tiket digital
        </template>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nama Lengkap</label>
                <input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="Nama sesuai KTP"
                />
                <div v-if="form.errors.name" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.name }}
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="nama@email.com"
                />
                <div v-if="form.errors.email" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700">Nomor WhatsApp / HP</label>
                <input
                    id="phone"
                    type="tel"
                    v-model="form.phone"
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="08xxxxxxxxxx"
                />
                <div v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.phone }}
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
                    {{ form.processing ? 'Memproses Pendaftaran...' : 'Daftar Sekarang' }}
                </button>
            </div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-600">
                Sudah punya akun?
                <Link href="/login" class="font-semibold text-blue-600 hover:text-blue-500 ml-1">
                    Masuk di sini
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
