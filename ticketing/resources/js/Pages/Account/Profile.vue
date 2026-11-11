<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { SharedProps } from '../../types';

const page = usePage<SharedProps>();
const user = page.props.auth.user;

const profileForm = useForm({
    name: user?.name || '',
    email: user?.email || '',
    phone: user?.phone || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updateProfile = () => {
    profileForm.patch('/profile', {
        preserveScroll: true,
    });
};

const updatePassword = () => {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Profil Akun" />

        <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8 space-y-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Pengaturan Akun</h1>
                <p class="text-sm text-slate-600 mt-1">Kelola data profil pribadi dan keamanan akun Anda.</p>
            </div>

            <!-- Profile Form -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-8 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900 mb-1">Informasi Profil</h2>
                <p class="text-xs text-slate-500 mb-6">Perbarui nama, alamat email, dan nomor telepon akun Anda.</p>

                <form @submit.prevent="updateProfile" class="space-y-4 max-w-xl">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700">Nama Lengkap</label>
                        <input
                            id="name"
                            type="text"
                            v-model="profileForm.name"
                            required
                            class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        />
                        <div v-if="profileForm.errors.name" class="mt-1 text-xs text-rose-600">
                            {{ profileForm.errors.name }}
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                        <input
                            id="email"
                            type="email"
                            v-model="profileForm.email"
                            required
                            class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        />
                        <div v-if="profileForm.errors.email" class="mt-1 text-xs text-rose-600">
                            {{ profileForm.errors.email }}
                        </div>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700">Nomor WhatsApp / HP</label>
                        <input
                            id="phone"
                            type="tel"
                            v-model="profileForm.phone"
                            class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                            placeholder="08xxxxxxxxxx"
                        />
                        <div v-if="profileForm.errors.phone" class="mt-1 text-xs text-rose-600">
                            {{ profileForm.errors.phone }}
                        </div>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
                        >
                            {{ profileForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Form -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-8 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900 mb-1">Perbarui Kata Sandi</h2>
                <p class="text-xs text-slate-500 mb-6">Pastikan akun Anda menggunakan kata sandi yang panjang dan acak agar tetap aman.</p>

                <form @submit.prevent="updatePassword" class="space-y-4 max-w-xl">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-slate-700">Kata Sandi Saat Ini</label>
                        <input
                            id="current_password"
                            type="password"
                            v-model="passwordForm.current_password"
                            required
                            autocomplete="current-password"
                            class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        />
                        <div v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-rose-600">
                            {{ passwordForm.errors.current_password }}
                        </div>
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-slate-700">Kata Sandi Baru</label>
                        <input
                            id="new_password"
                            type="password"
                            v-model="passwordForm.password"
                            required
                            autocomplete="new-password"
                            class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                            placeholder="Minimal 8 karakter"
                        />
                        <div v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-600">
                            {{ passwordForm.errors.password }}
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Ulangi Kata Sandi Baru</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            v-model="passwordForm.password_confirmation"
                            required
                            class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                        />
                        <div v-if="passwordForm.errors.password_confirmation" class="mt-1 text-xs text-rose-600">
                            {{ passwordForm.errors.password_confirmation }}
                        </div>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
                        >
                            {{ passwordForm.processing ? 'Menyimpan...' : 'Ganti Kata Sandi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
