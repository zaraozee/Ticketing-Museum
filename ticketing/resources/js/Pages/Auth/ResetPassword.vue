<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Atur Ulang Kata Sandi" />

        <template #subtitle>
            Buat kata sandi baru untuk akun Anda
        </template>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                />
                <div v-if="form.errors.email" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi Baru</label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autofocus
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
                    placeholder="Ulangi kata sandi baru"
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
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Kata Sandi Baru' }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
