<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk Akun" />

        <template #subtitle>
            Masuk ke akun Anda untuk melihat tiket atau mengelola museum
        </template>

        <!-- Social Login: Google -->
        <div class="mb-6">
            <a
                href="/auth/google"
                class="w-full flex items-center justify-center gap-3 py-2.5 px-4 border border-slate-300 rounded-xl text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition shadow-sm"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" />
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" />
                </svg>
                Masuk dengan Google
            </a>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200" />
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-white px-3 text-slate-500 font-medium">Atau dengan Email</span>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="nama@email.com"
                />
                <div v-if="form.errors.email" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                    <Link
                        href="/forgot-password"
                        class="text-xs font-semibold text-blue-600 hover:text-blue-500"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="••••••••"
                />
                <div v-if="form.errors.password" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.password }}
                </div>
            </div>

            <div class="flex items-center">
                <input
                    id="remember"
                    type="checkbox"
                    v-model="form.remember"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <label for="remember" class="ml-2 block text-xs text-slate-700">Ingat saya</label>
            </div>

            <div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
                >
                    {{ form.processing ? 'Memproses...' : 'Masuk' }}
                </button>
            </div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100 text-center space-y-2">
            <p class="text-xs text-slate-600">
                Belum punya akun pengunjung?
                <Link href="/register" class="font-semibold text-blue-600 hover:text-blue-500 ml-1">
                    Daftar di sini
                </Link>
            </p>
            <p class="text-xs text-slate-500">
                Pengelola museum?
                <Link href="/register/organizer" class="font-semibold text-slate-700 hover:underline ml-1">
                    Daftar Akun Pengelola
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
