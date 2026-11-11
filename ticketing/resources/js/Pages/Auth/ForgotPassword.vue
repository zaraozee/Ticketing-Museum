<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Kata Sandi" />

        <template #subtitle>
            Masukkan email Anda untuk menerima tautan pemulihan kata sandi
        </template>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    class="mt-1 block w-full px-3.5 py-2 rounded-xl border border-slate-300 text-slate-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="nama@email.com"
                />
                <div v-if="form.errors.email" class="mt-1 text-xs text-rose-600">
                    {{ form.errors.email }}
                </div>
            </div>

            <div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full flex justify-center py-2.5 px-4 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
                >
                    {{ form.processing ? 'Mengirim...' : 'Kirim Tautan Pemulihan' }}
                </button>
            </div>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100 text-center">
            <Link href="/login" class="text-xs font-semibold text-blue-600 hover:text-blue-500">
                &larr; Kembali ke halaman Masuk
            </Link>
        </div>
    </GuestLayout>
</template>
